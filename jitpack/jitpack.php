<?php
require_once('lib/cssmin.php');     // load cssmin library
require_once('lib/jsmin.php');      // load jsmin library
require_once('lib/lessc.inc.php');  // load LESS library

class JITpack {
    
    private $_minify_enabled = true;
    private $_cache_file_path;

    public function __construct()
    {
        // load config
        $config = parse_ini_file('jitpack.ini', true);
        
        // minification is enabled if it is not turned off in .ini or by env variable
        $this->_minify_enabled = ( !empty($config['minify']) && getenv('JITPACK_MINIFY') !== 'Off' );
        
        // read filename
        $filename = ( isset($_GET['file']) ) ? $_GET['file'] : null;
        
        if ( !empty($filename) ) {

            // get file path info
            $file = pathinfo($filename);

            // if asset group exists...
            if ( !empty($file['extension']) 
              && !empty($config[$filename]) 
              && !empty($config[$filename][$file['extension']]) 
            ) {

                // asset group files array
                $assets = $config[$filename][$file['extension']];

                // if an asset group is defined...
                if ( !empty($assets) ) {
                    
                    // concat the cache file path
                    $this->_cache_file_path = "../{$config['cache_dir']}/$filename";

                    // create a new cache file
                    $result = $this->_pack($assets);
                    
                     // if cache/packed file was created successfully...
                    if ( $result ) {

                        // determine the mime type for the HTTP response header
                        if ( $file['extension'] == 'css' ) {
                            $mime = 'text/css';
                        } else if ( $file['extension'] == 'js' ) {
                            $mime = 'application/javascript';
                        }

                        // send the header
                        header('Content-Type: ' . $mime);

                        // set last modified header for caching
                        header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', filemtime($this->_cache_file_path)));

                        // output the packed contents
                        readfile($this->_cache_file_path);

                    } else {                
                        // else, there was an error - respond with 500
                        header('HTTP/1.0 500 Internal Server Error', true, 500);
                        exit;                
                    }
                    
                }

            } else {
                // asset group is not defined for this file - 404
                header('HTTP/1.0 404 Not Found', true, 404);
                exit;
            }
            
        } else {                
            // else, there was an error - respond with 500
            header('HTTP/1.0 500 Internal Server Error', true, 500);
            exit;
        }

    }

    private function _pack( $assets = array() )
    {        
        $return = false;
        
        if ( !empty($assets) ) {
            
            $buffer = '';

            foreach ( $assets as $asset ) {
                
                $asset = '../' . $asset;

                if ( file_exists($asset) ) {

                    // read file contents
                    $contents = file_get_contents($asset);

                    // read file info
                    $pathinfo = pathinfo($asset);

                    // store the asset file extension
                    $extension = $pathinfo['extension'];

                    // if LESS, parse into CSS
                    if ( $extension == 'less' ) {        
                        if ( !isset($less) ) {
                            $less = new lessc;
                        }

                        try {
                            $contents = $less->compile($contents);
                        } catch (exception $e) {
                            error_log('LESS Compiler Error: '. $e->getMessage());
                        }
                    }

                    // prepend semi-colon to JS files to avoid confilicts
                    // due to combining
                    if ( $extension == 'js' ) {
                        $buffer .= ';'; 
                    }
                
                    $buffer .= $contents . "\n";
                    
                } else {
                    error_log("JITpack Error: Asset file $asset not found.");
                }

            }

            // minify, if enabled
            if ( $this->_minify_enabled ) {
                if ( $extension == 'css' || $extension == 'less' ) {
                    $buffer = CssMin::minify($buffer);
                } else if ( $extension == 'js' ) {
                    $buffer = JSMin::minify($buffer);
                }
            }

            if ( !empty($buffer) ) {
                // write new cache file
                $result = file_put_contents($this->_cache_file_path, $buffer);

                if ( $result !== false ) {            
                    // set permissions
                    chmod($this->_cache_file_path, 0777);                    
                    $return = true;
                } else {                
                    // else, there was an error - respond with 500
                    header('HTTP/1.0 500 Internal Server Error', true, 500);
                    exit;
                }
            }
            
        }
        
        return $return;        
    }

}

// off we go...
new JITpack();