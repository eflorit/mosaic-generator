<?php

class Mosaic {
    private $photos_dir           = './photos';     // NO TRAILING SLASH. Directory where original full size photos are stored
    private $thumbs_dir           = './thumbnails'; // NO TRAILING SLASH. Directory where thumbnails will be generated
    private $enhance_colors       = 27; // % or false
    private $min_space_same_thumb = 4; // prevents use for 2 thumbs around a same area
    private $logging              = true;
    private $db_config = array(
        'host'     => 'localhost',
        'username' => 'root',
        'pwd'      => 'password',
        'db_name'  => 'mosaic'
    );

    private $db;
    private $input;
    private $cell   = array();
    private $thumbs = array();
    private $matrix = array();
    private $output;
    private $output_filename;

    private $input_filename;
    private $rows;
    private $columns;
    private $gen_thumbs;

    function __construct($input_filename, $rows, $columns, $gen_thumbs = true) {
        $this->input_filename = $input_filename;
        $this->rows           = $rows;
        $this->columns        = $columns;
        $this->gen_thumbs     = $gen_thumbs;

        $this->log('Started at: '.date('H:i:s'));

        $this->prepare();

        $this->generate();

        $this->log('Ended at: '.date('H:i:s'));

        return $this->save();
    }

    // makes sure all resources are ready and valid
    private function prepare() {
        $this->input['img']    = imagecreatefromjpeg($this->input_filename);
        $this->input['width']  = imagesx($this->input['img']);
        $this->input['height'] = imagesy($this->input['img']);

        $this->db = mysqli_connect(
            $this->db_config['host'],
            $this->db_config['username'],
            $this->db_config['pwd'],
            $this->db_config['db_name']
        );

        if(!mysqli_select_db($this->db, $this->db_config['db_name']))
            throw new Exception('Database error');

        if($this->input['width'] % $this->columns)
            throw new Exception($this->columns.' not a multiple of '.$this->input['width']);
        if($this->input['height'] % $this->rows)
            throw new Exception($this->rows.' not a multiple of '.$this->input['height']);

        $this->cell = array(
            'width'  => $this->input['width']  / $this->columns,
            'height' => $this->input['height'] / $this->rows
        );

        if(!is_dir($this->thumbs_dir)) throw new Exception('"'.$this->thumbs_dir.'" does not exist');
        if($this->gen_thumbs) $this->gen_thumbs();
        $this->load_thumbs();
    }

    // (re)generates thumbnails and determines average color for each image
    private function gen_thumbs() {

        $this->log('Regenerating thumbnails... This may take up to a few minutes.');
        
        exec('rm -f '.$this->thumbs_dir.'/*');
        mysqli_query($this->db, 'TRUNCATE TABLE thumbnails');
        
        $images = scandir($this->photos_dir);
        $images = array_slice($images, 2); // '..', '.'

        if(!count($images)) throw new Exception('No photos to process in '.$this->thumbs_dir);

        $now = time();
        foreach($images as $i) {
            switch(strtolower(substr($i,-4)))
            {
                case '.jpg':
                case 'jpeg':
                $img = imagecreatefromjpeg($this->photos_dir.'/'.$i);
                break;
                case '.png':
                $img = imagecreatefrompng($this->photos_dir.'/'.$i);
                break;
                case '.gif':
                $img = imagecreatefromgif($this->photos_dir.'/'.$i);
                break;
            }
                    
            $thumb = imagecreatetruecolor($this->cell['width'], $this->cell['height']);
            imagecopyresampled($thumb, $img, 0, 0, 0, 0, $this->cell['width'], $this->cell['height'], imagesx($img), imagesy($img));
            
            $filename = md5($i.$now).'.jpg';
            $info = array_merge($this->get_avg_color($thumb), array('"'.$filename.'"'));
            imagejpeg($thumb, './thumbnails/'.$filename, 95);
            mysqli_query($this->db, 'INSERT INTO thumbnails (red, green, blue, filename) VALUES ('.implode(',', $info).')');
        }

    }

    // fetches thumbnail info from db
    private function load_thumbs() {
        $output = mysqli_query($this->db, 'SELECT * FROM thumbnails');
        if(!mysqli_num_rows($output)) throw new Exception('No thumbs in db. Please (re)generate thumbs.');
        while($row = mysqli_fetch_assoc($output)) {
            array_push($this->thumbs, array($row['red'], $row['green'], $row['blue'], $row['filename']));
        }
    }

    private function generate() {
        // determines size of a cell
        $this->output = $this->input['img'];
        $blank_cell = imagecreatetruecolor($this->cell['width'], $this->cell['height']);
        $blank_row  = imagecreatetruecolor($this->cell['width'] * $this->columns, $this->cell['height']);

        $this->log('Progress: 0%');

        // loops through every "cell" (rows/columns)
        for($i = 0; $i < $this->rows; $i++) {
            $row = $blank_row;
            for($j=0; $j < $this->columns; $j++) {
            
                // gets next cell to process
                $current_cell = $blank_cell;
                imagecopy($current_cell, $this->input['img'], 0, 0, $j * $this->cell['width'], $i * $this->cell['height'], $this->input['width'], $this->input['height']);
                $color_code = $this->get_avg_color($current_cell);
                
                $current_cell = imagecreatefromjpeg($this->thumbs_dir.'/'.$this->get_filename_closest_color($color_code, $i, $j));

                // cheats by tweaking colors for better accuracy
                if($this->enhance_colors != false) {
                    $tweak = $blank_cell;
                    $color_resource = imagecolorallocate($tweak, $color_code[0], $color_code[1], $color_code[2]);
                    imagefill($tweak, 0, 0, $color_resource);
                    imagecopymerge($tweak, $current_cell, 0, 0, 0, 0, $this->cell['width'], $this->cell['height'], 100 - $this->enhance_colors);
                    $current_cell = $tweak;
                }
                
                // row done processing
                imagecopy($row, $current_cell, $j * $this->cell['width'], 0, 0, 0, $this->cell['width'], $this->cell['height']);
            }
            
            // photo done processing
            imagecopy($this->output, $row, 0, $i * $this->cell['height'], 0, 0, $this->cell['width'] * $this->columns, $this->cell['height']);

            $this->log('Progress: '.round(($i + 1) / $this->rows * 100).'%', true);
        }

        $this->log('Progress: done.', true);
    }

    // saves GD resource to file
    private function save() {
        $filename = './output-'.time().'.jpg';
        imagejpeg($this->output, './'.$filename, 99);

        $this->log('Saved: '.$filename."\n");

        return $filename;
    }

    // determines average color of an image
    private function get_avg_color($img) {

        $w = imagesx($img);
        $h = imagesy($img);
        
        $r = $g = $b = 0;

        for($y=0; $y<$h; $y++) {
              for($x=0; $x<$w; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r += $rgb >> 16;
                $g += $rgb >> 8 & 255;
                $b += $rgb & 255;
            }
        }
        
        $pxls = $w * $h;
        
        return array(
            round($r / $pxls),
            round($g / $pxls),
            round($b / $pxls)
        );
    }

    // determines which thumbnail is best suitable for specific color
    private function get_filename_closest_color($rgb, $coordY, $coordX) {
        
        $diffArray = array();
        
        foreach ($this->thumbs as $thumb) {
            $diffArray[$thumb[3]] = sqrt(pow(($rgb[0] - $thumb[0]) * 0.650,2) + pow(($rgb[1] - $thumb[1]) * 0.794,2) + pow(($rgb[2] - $thumb[2]) * 0.557,2));
        }
        asort($diffArray);
        $diffArray = array_keys($diffArray);
        
        // prevents use for 2 thumbs around a same area
        do {
            $suitable = true;
            $result   = array_shift($diffArray);
            
            for($i = ($coordY - $this->min_space_same_thumb) + 1; $i < ($coordY + $this->min_space_same_thumb); $i++) {
                for($j = ($coordX - $this->min_space_same_thumb + 1); $j < ($coordX + $this->min_space_same_thumb); $j++) {
                    if(isset($this->matrix[$i][$j]) && $this->matrix[$i][$j] == $result) {
                        $suitable = false;
                        break;
                    }
                }
            }
        } while($suitable == false);

        $this->matrix[$coordY][$coordX] = $result;
        
        return $result;
    }

    private function log($msg, $replace = false) {
        if($this->logging)
            print($replace ? "\r" : "\n").$msg;
    }
}
