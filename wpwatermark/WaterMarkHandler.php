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
     * 支持添加水印的 MIME 类型
     *
     * @return string[]
     */
    public static function getSupportedMimeTypes(): array {
        $mimes = ['image/jpeg', 'image/png', 'image/gif'];
        if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $mimes[] = 'image/webp';
        }
        return $mimes;
    }

    /**
     * 支持添加水印的扩展名（不带点）
     *
     * @return string[]
     */
    public static function getSupportedExtensions(): array {
        $extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $extensions[] = 'webp';
        }
        return $extensions;
    }
    
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
     * 九宫格位置键名列表（与 calculatePosition 的 case 一致）
     *
     * @return string[]
     */
    private static function getGridPositionKeys(): array {
        return [
            'top-left', 'top-center', 'top-right',
            'middle-left', 'middle-center', 'middle-right',
            'bottom-left', 'bottom-center', 'bottom-right',
        ];
    }

    /**
     * 若设置为 random，则每次处理在九宫格中随机选一格
     */
    private function resolveGridPosition(string $position): string {
        if ($position === 'random') {
            $grid = self::getGridPositionKeys();
            return $grid[wp_rand(0, count($grid) - 1)];
        }
        return $position;
    }

    /**
     * 从本次调用的 options 与默认配置得到原始位置字符串（可能为 random）
     */
    private function getRawWatermarkPosition(array $options): string {
        if (isset($options['watermark_position']) && $options['watermark_position'] !== '') {
            return (string) $options['watermark_position'];
        }
        if (isset($options['position']) && $options['position'] !== '') {
            return (string) $options['position'];
        }
        return (string) $this->options['watermark_position'];
    }
    
    /**
     * Check if cached version exists
     * 
     * @param string $cache_key
     * @return string|false
     */
    private function getCachedImage(string $cache_key) {
        $cache_extensions = ['jpg', 'png', 'gif', 'webp'];
        foreach ($cache_extensions as $ext) {
            $cache_file = $this->cache_dir . $cache_key . '.' . $ext;
            if (file_exists($cache_file) && (time() - filemtime($cache_file) < 3600)) {
                return $cache_file;
            }
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
            $merged = array_merge($this->options, $options);
            $raw_position = $this->getRawWatermarkPosition($options);
            $use_cache = ($raw_position !== 'random');

            if ($use_cache) {
                $cache_key = $this->generateCacheKey($img_url, $merged);
                $cached_file = $this->getCachedImage($cache_key);
                if ($cached_file) {
                    copy($cached_file, $new_img_url);
                    return true;
                }
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
            $position = $this->calculatePosition(
                $this->resolveGridPosition($raw_position),
                $img_size[0],
                $img_size[1],
                $text
            );
            
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
            
            // Cache result（随机位置不使用缓存，避免多次上传被同一随机结果锁死）
            if ($use_cache) {
                copy($new_img_url, $this->cache_dir . $cache_key . '.jpg');
            }
            
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
    public function createImageWatermark($img_url, $watermark_url, $new_img_url, $options = []) {
        try {
            $merged = array_merge($this->options, $options);
            $raw_position = $this->getRawWatermarkPosition($options);
            $use_cache = ($raw_position !== 'random');

            if ($use_cache) {
                $cache_key = $this->generateCacheKey($img_url, $merged);
                $cached_file = $this->getCachedImage($cache_key);
                if ($cached_file) {
                    copy($cached_file, $new_img_url);
                    return true;
                }
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
                $this->resolveGridPosition($raw_position),
                $img_size[0],
                $img_size[1],
                '',
                $watermark_size[0],
                $watermark_size[1]
            );

            // 创建临时图像用于处理
            $temp = imagecreatetruecolor($img_size[0], $img_size[1]);
            
            // 设置临时图像的透明度支持
            imagealphablending($temp, false);
            imagesavealpha($temp, true);
            
            // 如果原图是PNG，设置透明背景
            if ($img_size['mime'] === 'image/png' || $img_size['mime'] === 'image/webp') {
                $transparent = imagecolorallocatealpha($temp, 0, 0, 0, 127);
                imagefilledrectangle($temp, 0, 0, $img_size[0], $img_size[1], $transparent);
            }
            
            // 复制原图到临时图像
            imagecopy($temp, $im, 0, 0, 0, 0, $img_size[0], $img_size[1]);
            
            // 获取透明度设置
            $opacity = ($options['watermark_diaphaneity'] ?? $this->options['watermark_diaphaneity']);
            
            // 如果是PNG水印，保持其原有透明度
            if ($watermark_size['mime'] === 'image/png' || $watermark_size['mime'] === 'image/webp') {
                // 创建水印临时图像
                $watermark_temp = imagecreatetruecolor($watermark_size[0], $watermark_size[1]);
                imagealphablending($watermark_temp, false);
                imagesavealpha($watermark_temp, true);
                
                // 设置完全透明背景
                $transparent = imagecolorallocatealpha($watermark_temp, 0, 0, 0, 127);
                imagefilledrectangle($watermark_temp, 0, 0, $watermark_size[0], $watermark_size[1], $transparent);
                
                // 复制水印到临时图像
                imagecopy($watermark_temp, $watermark, 0, 0, 0, 0, $watermark_size[0], $watermark_size[1]);
                
                // 应用用户设置的透明度
                if ($opacity < 100) {
                    // 逐像素调整透明度
                    for ($x = 0; $x < $watermark_size[0]; $x++) {
                        for ($y = 0; $y < $watermark_size[1]; $y++) {
                            $color = imagecolorsforindex($watermark_temp, imagecolorat($watermark_temp, $x, $y));
                            $alpha = 127 - ((127 - $color['alpha']) * $opacity / 100);
                            $new_color = imagecolorallocatealpha(
                                $watermark_temp,
                                $color['red'],
                                $color['green'],
                                $color['blue'],
                                intval($alpha)
                            );
                            imagesetpixel($watermark_temp, $x, $y, $new_color);
                        }
                    }
                }
                
                // 合并水印到目标图像
                imagealphablending($temp, true);
                imagecopy($temp, $watermark_temp, $position['x'], $position['y'], 0, 0, $watermark_size[0], $watermark_size[1]);
                imagedestroy($watermark_temp);
            } else {
                // 非PNG水印的处理
                imagealphablending($temp, true);
                $this->imagecopymerge_alpha(
                    $temp, $watermark,
                    $position['x'], $position['y'],
                    0, 0,
                    $watermark_size[0], $watermark_size[1],
                    $opacity
                );
            }
            
            // 保存最终图像
            if ($img_size['mime'] === 'image/png' || $img_size['mime'] === 'image/webp') {
                imagealphablending($temp, false);
                imagesavealpha($temp, true);
            }
            $this->saveImage($temp, $new_img_url, $img_size['mime']);
            
            if ($use_cache) {
                $cache_ext = 'jpg';
                if ($img_size['mime'] === 'image/png') {
                    $cache_ext = 'png';
                } elseif ($img_size['mime'] === 'image/gif') {
                    $cache_ext = 'gif';
                } elseif ($img_size['mime'] === 'image/webp') {
                    $cache_ext = 'webp';
                }
                $cache_file = $this->cache_dir . $cache_key . '.' . $cache_ext;
                copy($new_img_url, $cache_file);
            }
            
            // 清理资源
            imagedestroy($temp);
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

        if (function_exists('imagecreatefromwebp')) {
            $create_functions['image/webp'] = 'imagecreatefromwebp';
        }
        
        if (isset($create_functions[$mime_type])) {
            $im = call_user_func($create_functions[$mime_type], $img_url);
            
            // 特别处理PNG图片的透明度
            if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
                imagealphablending($im, false);
                imagesavealpha($im, true);
            }
            
            return $im;
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
        switch ($mime_type) {
            case 'image/jpeg':
                return imagejpeg($im, $filename, 95); // 95% quality for JPEG
            case 'image/png':
                return imagepng($im, $filename, 0); // 0-9, 0 for no compression to maintain quality
            case 'image/gif':
                return imagegif($im, $filename);
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    return imagewebp($im, $filename, 95);
                }
                return false;
            default:
                return false;
        }
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
    private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct) {
        // 确保透明度在有效范围内
        $pct = min(100, max(0, $pct));
        
        // 创建临时图像
        $cut = imagecreatetruecolor($src_w, $src_h);
        
        // 设置完全透明背景
        imagealphablending($cut, false);
        imagesavealpha($cut, true);
        $transparent = imagecolorallocatealpha($cut, 0, 0, 0, 127);
        imagefilledrectangle($cut, 0, 0, $src_w, $src_h, $transparent);
        
        // 复制目标区域到临时图像
        imagecopy($cut, $dst_im, 0, 0, intval($dst_x), intval($dst_y), $src_w, $src_h);
        
        // 启用混合模式
        imagealphablending($cut, true);
        
        // 应用水印到临时图像
        $this->imagecopymerge_alpha_pixel($cut, $src_im, 0, 0, intval($src_x), intval($src_y), $src_w, $src_h, $pct);
        
        // 保持目标图像的透明度
        imagealphablending($dst_im, true);
        imagesavealpha($dst_im, true);
        
        // 将处理后的临时图像复制回目标图像
        imagecopy($dst_im, $cut, intval($dst_x), intval($dst_y), 0, 0, $src_w, $src_h);
        
        // 清理
        imagedestroy($cut);
    }
    
    /**
     * 逐像素处理透明度
     */
    private function imagecopymerge_alpha_pixel($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct) {
        if ($pct == 0) return;
        
        // 逐像素处理
        for ($y = 0; $y < $src_h; ++$y) {
            for ($x = 0; $x < $src_w; ++$x) {
                $src_color = imagecolorsforindex($src_im, imagecolorat($src_im, $src_x + $x, $src_y + $y));
                $dst_color = imagecolorsforindex($dst_im, imagecolorat($dst_im, $dst_x + $x, $dst_y + $y));
                
                // 计算新的透明度
                $src_alpha = 127 - ($src_color['alpha'] * $pct / 100);
                $dst_alpha = 127 - $dst_color['alpha'];
                $final_alpha = 127 - (($src_alpha + $dst_alpha) / 2);
                
                // 混合颜色
                $final_red = ($src_color['red'] * $pct + $dst_color['red'] * (100 - $pct)) / 100;
                $final_green = ($src_color['green'] * $pct + $dst_color['green'] * (100 - $pct)) / 100;
                $final_blue = ($src_color['blue'] * $pct + $dst_color['blue'] * (100 - $pct)) / 100;
                
                // 创建新颜色
                $final_color = imagecolorallocatealpha(
                    $dst_im,
                    intval($final_red),
                    intval($final_green),
                    intval($final_blue),
                    intval($final_alpha)
                );
                
                // 设置像素
                imagesetpixel($dst_im, $dst_x + $x, $dst_y + $y, $final_color);
            }
        }
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
    private function calculatePosition($position, $img_width, $img_height, $text = '', $mark_width = 0, $mark_height = 0) {
        $margin = intval($this->options['watermark_margin']);
        
        // For text watermark
        if ($text !== '') {
            $font_size = $this->options['text_size'];
            $font_file = $this->font_dir . $this->options['text_font'];
            $text_box = imagettfbbox($font_size, 0, $font_file, $text);
            $mark_width = abs($text_box[4] - $text_box[0]);
            $mark_height = abs($text_box[1] - $text_box[5]);
        }
        
        // Calculate grid dimensions
        $grid_width = $img_width / 3;
        $grid_height = $img_height / 3;
        
        // Calculate position based on grid
        switch ($position) {
            case 'top-left':
                return [
                    'x' => $margin,
                    'y' => $margin
                ];
            
            case 'top-center':
                return [
                    'x' => intval($grid_width + ($grid_width - $mark_width) / 2),
                    'y' => $margin
                ];
            
            case 'top-right':
                return [
                    'x' => intval($img_width - $mark_width - $margin),
                    'y' => $margin
                ];
            
            case 'middle-left':
                return [
                    'x' => $margin,
                    'y' => intval($grid_height + ($grid_height - $mark_height) / 2)
                ];
            
            case 'middle-center':
                return [
                    'x' => intval($grid_width + ($grid_width - $mark_width) / 2),
                    'y' => intval($grid_height + ($grid_height - $mark_height) / 2)
                ];
            
            case 'middle-right':
                return [
                    'x' => intval($img_width - $mark_width - $margin),
                    'y' => intval($grid_height + ($grid_height - $mark_height) / 2)
                ];
            
            case 'bottom-left':
                return [
                    'x' => $margin,
                    'y' => intval($img_height - $mark_height - $margin)
                ];
            
            case 'bottom-center':
                return [
                    'x' => intval($grid_width + ($grid_width - $mark_width) / 2),
                    'y' => intval($img_height - $mark_height - $margin)
                ];
            
            case 'bottom-right':
            default:
                return [
                    'x' => intval($img_width - $mark_width - $margin),
                    'y' => intval($img_height - $mark_height - $margin)
                ];
        }
    }
} 