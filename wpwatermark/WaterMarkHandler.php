<?php
/**
 * WaterMark Handler Class
 * 
 * Handles all watermark related operations with improved organization and caching
 */
class WaterMarkHandler {
    /** @var string */
    private $cache_dir;
    
    /** @var string */
    private $font_dir;
    
    /** @var array */
    private $options;
    
    /**
     * Constructor
     * 
     * @param array $options Watermark options
     */
    public function __construct(array $options) {
        $this->options = $options;
        $this->cache_dir = plugin_dir_path(__FILE__) . 'cache/';
        $this->font_dir = plugin_dir_path(__FILE__) . 'fonts/';
        
        // Ensure cache directory exists
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }
    
    /**
     * Generate cache key for watermark options
     * 
     * @param string $img_url
     * @param array $options
     * @return string
     */
    private function generateCacheKey(string $img_url, array $options): string {
        return md5($img_url . serialize($options));
    }
    
    /**
     * Check if cached version exists
     * 
     * @param string $cache_key
     * @return string|false
     */
    private function getCachedImage(string $cache_key) {
        $cache_file = $this->cache_dir . $cache_key . '.jpg';
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < 3600)) {
            return $cache_file;
        }
        return false;
    }
    
    /**
     * Create text watermark with improved error handling and caching
     * 
     * @param string $img_url
     * @param string $new_img_url
     * @param string $text
     * @param array $options
     * @return bool
     */
    public function createTextWatermark(string $img_url, string $new_img_url, string $text, array $options = []): bool {
        try {
            // Generate cache key
            $cache_key = $this->generateCacheKey($img_url, array_merge($this->options, $options));
            
            // Check cache
            $cached_file = $this->getCachedImage($cache_key);
            if ($cached_file) {
                copy($cached_file, $new_img_url);
                return true;
            }
            
            // Validate image
            $img_size = getimagesize($img_url);
            if (empty($img_size)) {
                throw new Exception('Invalid image file');
            }
            
            // Validate dimensions
            if ($img_size[0] < $this->options['watermark_min_width'] || 
                $img_size[1] < $this->options['watermark_min_height']) {
                throw new Exception('Image dimensions too small for watermark');
            }
            
            // Create image resource
            $im = $this->createImageResource($img_url, $img_size['mime']);
            if (!$im) {
                throw new Exception('Failed to create image resource');
            }
            
            // Apply text watermark
            $text_color = $this->parseColor($options['text_color'] ?? $this->options['text_color']);
            $font_file = $this->font_dir . ($options['text_font'] ?? $this->options['text_font']);
            
            if (!file_exists($font_file)) {
                throw new Exception('Font file not found');
            }
            
            $text_color = imagecolorallocate($im, $text_color['r'], $text_color['g'], $text_color['b']);
            $position = $this->calculatePosition($options['position'] ?? $this->options['watermark_position'], 
                                               $img_size[0], 
                                               $img_size[1], 
                                               $text);
            
            // Add watermark
            imagettftext(
                $im,
                $options['text_size'] ?? $this->options['text_size'],
                $options['text_angle'] ?? $this->options['text_angle'],
                $position['x'],
                $position['y'],
                $text_color,
                $font_file,
                $text
            );
            
            // Save image
            $this->saveImage($im, $new_img_url, $img_size['mime']);
            
            // Cache result
            copy($new_img_url, $this->cache_dir . $cache_key . '.jpg');
            
            imagedestroy($im);
            return true;
            
        } catch (Exception $e) {
            error_log('WaterMark Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create image watermark with improved error handling and caching
     * 
     * @param string $img_url
     * @param string $watermark_url
     * @param string $new_img_url
     * @param array $options
     * @return bool
     */
    public function createImageWatermark(string $img_url, string $watermark_url, string $new_img_url, array $options = []): bool {
        try {
            // Generate cache key
            $cache_key = $this->generateCacheKey($img_url, array_merge($this->options, $options));
            
            // Check cache
            $cached_file = $this->getCachedImage($cache_key);
            if ($cached_file) {
                copy($cached_file, $new_img_url);
                return true;
            }
            
            // Validate image
            $img_size = getimagesize($img_url);
            if (empty($img_size)) {
                throw new Exception('Invalid image file');
            }
            
            // Validate dimensions
            if ($img_size[0] < $this->options['watermark_min_width'] || 
                $img_size[1] < $this->options['watermark_min_height']) {
                throw new Exception('Image dimensions too small for watermark');
            }
            
            // Create image resource
            $im = $this->createImageResource($img_url, $img_size['mime']);
            if (!$im) {
                throw new Exception('Failed to create image resource');
            }
            
            // Load watermark image
            $watermark_size = getimagesize($watermark_url);
            if (!$watermark_size) {
                throw new Exception('Invalid watermark image');
            }
            
            $watermark = $this->createImageResource($watermark_url, $watermark_size['mime']);
            if (!$watermark) {
                throw new Exception('Failed to create watermark resource');
            }
            
            // Calculate position
            $position = $this->calculatePosition(
                $options['watermark_position'] ?? $this->options['watermark_position'],
                $img_size[0],
                $img_size[1],
                '',
                $watermark_size[0],
                $watermark_size[1]
            );
            
            // Set transparency
            $opacity = ($options['watermark_diaphaneity'] ?? $this->options['watermark_diaphaneity']) / 100;
            imagealphablending($watermark, true);
            imagesavealpha($watermark, true);
            
            // Apply watermark
            $this->imagecopymerge_alpha($im, $watermark, $position['x'], $position['y'], 0, 0, 
                                      $watermark_size[0], $watermark_size[1], $opacity * 100);
            
            // Save image
            $this->saveImage($im, $new_img_url, $img_size['mime']);
            
            // Cache result
            copy($new_img_url, $this->cache_dir . $cache_key . '.jpg');
            
            // Clean up
            imagedestroy($im);
            imagedestroy($watermark);
            
            return true;
            
        } catch (Exception $e) {
            error_log('WaterMark Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper function to create image resource
     * 
     * @param string $img_url
     * @param string $mime_type
     * @return resource|false
     */
    private function createImageResource(string $img_url, string $mime_type) {
        $create_functions = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
            'image/gif'  => 'imagecreatefromgif'
        ];
        
        if (isset($create_functions[$mime_type])) {
            return call_user_func($create_functions[$mime_type], $img_url);
        }
        
        return false;
    }
    
    /**
     * Helper function to save image
     * 
     * @param resource $im
     * @param string $filename
     * @param string $mime_type
     * @return bool
     */
    private function saveImage($im, string $filename, string $mime_type): bool {
        $save_functions = [
            'image/jpeg' => 'imagejpeg',
            'image/png'  => 'imagepng',
            'image/gif'  => 'imagegif'
        ];
        
        if (isset($save_functions[$mime_type])) {
            return call_user_func($save_functions[$mime_type], $im, $filename);
        }
        
        return false;
    }
    
    /**
     * Parse hex color to RGB
     */
    private function parseColor($hex_color) {
        $hex_color = ltrim($hex_color, '#');
        return [
            'r' => hexdec(substr($hex_color, 0, 2)),
            'g' => hexdec(substr($hex_color, 2, 2)),
            'b' => hexdec(substr($hex_color, 4, 2))
        ];
    }
    
    /**
     * Helper function for alpha-enabled imagecopymerge
     * 
     * @param resource $dst_im
     * @param resource $src_im
     * @param int $dst_x
     * @param int $dst_y
     * @param int $src_x
     * @param int $src_y
     * @param int $src_w
     * @param int $src_h
     * @param int $pct
     * @return void
     */
    private function imagecopymerge_alpha($dst_im, $src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h, int $pct): void {
        // Create a new transparent image
        $cut = imagecreatetruecolor($src_w, $src_h);
        imagealphablending($cut, false);
        imagesavealpha($cut, true);
        
        // Copy source image
        imagecopy($cut, $dst_im, 0, 0, intval($dst_x), intval($dst_y), $src_w, $src_h);
        
        // Copy watermark with transparency
        imagecopy($cut, $src_im, 0, 0, intval($src_x), intval($src_y), $src_w, $src_h);
        imagecopymerge($dst_im, $cut, intval($dst_x), intval($dst_y), 0, 0, $src_w, $src_h, intval($pct));
        
        imagedestroy($cut);
    }
    
    /**
     * Calculate watermark position
     * 
     * @param string $position
     * @param int $img_width
     * @param int $img_height
     * @param string $text
     * @param int $mark_width
     * @param int $mark_height
     * @return array{x: int, y: int}
     */
    private function calculatePosition(string $position, int $img_width, int $img_height, string $text = '', int $mark_width = 0, int $mark_height = 0): array {
        $margin = intval($this->options['watermark_margin']);
        
        // For text watermark
        if ($text !== '') {
            $font_size = $this->options['text_size'];
            $font_file = $this->font_dir . $this->options['text_font'];
            $text_box = imagettfbbox($font_size, 0, $font_file, $text);
            $mark_width = abs($text_box[4] - $text_box[0]);
            $mark_height = abs($text_box[1] - $text_box[5]);
        }
        
        // Calculate position
        switch ($position) {
            case 'top-left':
                return [
                    'x' => $margin,
                    'y' => $margin + $mark_height
                ];
            
            case 'top-center':
                return [
                    'x' => intval(($img_width - $mark_width) / 2),
                    'y' => $margin + $mark_height
                ];
            
            case 'top-right':
                return [
                    'x' => $img_width - $mark_width - $margin,
                    'y' => $margin + $mark_height
                ];
            
            case 'middle-left':
                return [
                    'x' => $margin,
                    'y' => intval(($img_height + $mark_height) / 2)
                ];
            
            case 'middle-center':
                return [
                    'x' => intval(($img_width - $mark_width) / 2),
                    'y' => intval(($img_height + $mark_height) / 2)
                ];
            
            case 'middle-right':
                return [
                    'x' => $img_width - $mark_width - $margin,
                    'y' => intval(($img_height + $mark_height) / 2)
                ];
            
            case 'bottom-left':
                return [
                    'x' => $margin,
                    'y' => $img_height - $margin
                ];
            
            case 'bottom-center':
                return [
                    'x' => intval(($img_width - $mark_width) / 2),
                    'y' => $img_height - $margin
                ];
            
            case 'bottom-right':
            default:
                return [
                    'x' => $img_width - $mark_width - $margin,
                    'y' => $img_height - $margin
                ];
        }
    }
} 