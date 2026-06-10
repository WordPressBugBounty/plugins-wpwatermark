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
        
        // 缓存默认关闭，不再自动创建缓存目录
        if ($this->isCacheEnabled() && !file_exists($this->cache_dir)) {
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
     * 是否启用水印缓存（默认关闭，避免长期占用磁盘）
     */
    private function isCacheEnabled(): bool {
        return false;
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
            $use_cache = ($raw_position !== 'random') && $this->isCacheEnabled();

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
            
            $opacity = intval($options['watermark_diaphaneity'] ?? $this->options['watermark_diaphaneity']);
            $opacity = min(100, max(0, $opacity));
            $font_size = intval($options['text_size'] ?? $this->options['text_size']);
            $font_angle = intval($options['text_angle'] ?? $this->options['text_angle']);

            $position = $this->calculatePosition(
                $this->resolveGridPosition($raw_position),
                $img_size[0],
                $img_size[1],
                $text,
                0,
                0,
                $font_size,
                $font_angle,
                $font_file
            );
            
            $this->renderTextLayer(
                $im,
                $img_size[0],
                $img_size[1],
                $text,
                $font_file,
                $font_size,
                $font_angle,
                $position['x'],
                $position['y'],
                $text_color,
                $opacity
            );

            if ($img_size['mime'] === 'image/png' || $img_size['mime'] === 'image/webp') {
                imagealphablending($im, false);
                imagesavealpha($im, true);
            }
            
            // Save image
            if (!$this->saveImage($im, $new_img_url, $img_size['mime'], $img_url)) {
                throw new Exception('Failed to save watermarked image');
            }
            
            // Cache result（随机位置不使用缓存，避免多次上传被同一随机结果锁死）
            if ($use_cache) {
                $cache_ext = 'jpg';
                if ($img_size['mime'] === 'image/png') {
                    $cache_ext = 'png';
                } elseif ($img_size['mime'] === 'image/gif') {
                    $cache_ext = 'gif';
                } elseif ($img_size['mime'] === 'image/webp') {
                    $cache_ext = 'webp';
                }
                copy($new_img_url, $this->cache_dir . $cache_key . '.' . $cache_ext);
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
            $use_cache = ($raw_position !== 'random') && $this->isCacheEnabled();

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

            $scale_percent = intval($options['image_watermark_scale'] ?? $this->options['image_watermark_scale'] ?? 100);
            $scale_percent = min(100, max(1, $scale_percent));
            if ($scale_percent < 100) {
                $scaled_width = max(1, intval(round($watermark_size[0] * $scale_percent / 100)));
                $scaled_height = max(1, intval(round($watermark_size[1] * $scale_percent / 100)));
                $scaled_watermark = $this->resizeWatermarkResource(
                    $watermark,
                    $watermark_size[0],
                    $watermark_size[1],
                    $scaled_width,
                    $scaled_height
                );
                if ($scaled_watermark) {
                    imagedestroy($watermark);
                    $watermark = $scaled_watermark;
                    $watermark_size[0] = $scaled_width;
                    $watermark_size[1] = $scaled_height;
                }
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

            // 创建临时图像并正确复制原图（JPEG/GIF 不使用透明画布，避免原图被复制成透明）
            $temp = $this->createWorkingCanvas($img_size[0], $img_size[1], $img_size['mime']);
            $this->copyImageOntoCanvas($temp, $im, $img_size[0], $img_size[1], $img_size['mime']);
            
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
            if (!$this->saveImage($temp, $new_img_url, $img_size['mime'], $img_url)) {
                throw new Exception('Failed to save watermarked image');
            }
            
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
        $img_size = @getimagesize($img_url);
        $width = is_array($img_size) ? (int) $img_size[0] : 0;
        $height = is_array($img_size) ? (int) $img_size[1] : 0;

        $im = $this->createImageResourceWithGd($img_url, $mime_type);
        if ($im && !$this->isDecodedImageBroken($im, $width, $height, $mime_type)) {
            $this->normalizeImageResource($im, $mime_type);
            return $im;
        }
        if ($im) {
            imagedestroy($im);
        }

        $im = $this->createImageResourceFromString($img_url);
        if ($im && !$this->isDecodedImageBroken($im, $width, $height, $mime_type)) {
            $this->normalizeImageResource($im, $mime_type);
            return $im;
        }
        if ($im) {
            imagedestroy($im);
        }

        $im = $this->createImageResourceWithImagick($img_url, $mime_type);
        if ($im) {
            $this->normalizeImageResource($im, $mime_type);
            return $im;
        }

        return false;
    }

    /**
     * Decode image with native GD loaders.
     */
    private function createImageResourceWithGd(string $img_url, string $mime_type) {
        $create_functions = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
            'image/gif'  => 'imagecreatefromgif',
        ];

        if (function_exists('imagecreatefromwebp')) {
            $create_functions['image/webp'] = 'imagecreatefromwebp';
        }

        if (!isset($create_functions[$mime_type])) {
            return false;
        }

        return @call_user_func($create_functions[$mime_type], $img_url);
    }

    /**
     * Decode image from raw bytes; some optimizer outputs work here when GD loaders fail.
     */
    private function createImageResourceFromString(string $img_url) {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $bytes = @file_get_contents($img_url);
        if ($bytes === false || $bytes === '') {
            return false;
        }

        return @imagecreatefromstring($bytes);
    }

    /**
     * Decode image via Imagick for CMYK/ICC/progressive/optimizer outputs that GD mishandles.
     */
    private function createImageResourceWithImagick(string $img_url, string $mime_type) {
        if (!class_exists('Imagick')) {
            return false;
        }

        $imagick = null;

        try {
            $imagick = new Imagick();
            $imagick->readImage($img_url);

            if (defined('Imagick::COLORSPACE_SRGB') && method_exists($imagick, 'transformImageColorspace')) {
                $imagick->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            } elseif (defined('Imagick::COLORSPACE_RGB')) {
                $imagick->setImageColorspace(Imagick::COLORSPACE_RGB);
            }

            if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
                $imagick->setImageFormat('png');
            } elseif ($mime_type === 'image/gif') {
                $imagick->setImageFormat('gif');
            } else {
                $imagick->setImageBackgroundColor('white');
                if (defined('Imagick::ALPHACHANNEL_REMOVE')) {
                    $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                }
                if (method_exists($imagick, 'mergeImageLayers') && defined('Imagick::LAYERMETHOD_FLATTEN')) {
                    $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                }
                $imagick->setImageFormat('jpeg');
            }

            $resource = @imagecreatefromstring($imagick->getImagesBlob());
            $imagick->clear();
            $imagick->destroy();

            return $resource ?: false;
        } catch (Exception $e) {
            if ($imagick instanceof Imagick) {
                $imagick->clear();
                $imagick->destroy();
            }
            error_log('WaterMark Imagick conversion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Detect blank/solid-color decodes that often happen with compressed or ICC JPEG/PNG files.
     */
    private function isDecodedImageBroken($im, int $width, int $height, string $mime_type): bool {
        if ($width < 80 || $height < 80) {
            return false;
        }

        $sample_points = [
            [0, 0],
            [$width - 1, 0],
            [0, $height - 1],
            [$width - 1, $height - 1],
            [intval($width / 2), intval($height / 2)],
            [intval($width / 4), intval($height / 4)],
            [intval($width * 3 / 4), intval($height / 4)],
            [intval($width / 4), intval($height * 3 / 4)],
            [intval($width * 3 / 4), intval($height * 3 / 4)],
        ];

        $reds = [];
        $greens = [];
        $blues = [];
        $transparent_count = 0;

        foreach ($sample_points as $point) {
            [$x, $y] = $point;
            if ($x < 0 || $y < 0 || $x >= $width || $y >= $height) {
                continue;
            }

            $rgba = @imagecolorat($im, $x, $y);
            if ($rgba === false) {
                continue;
            }

            $color = imagecolorsforindex($im, $rgba);
            if (($mime_type === 'image/png' || $mime_type === 'image/webp') && $color['alpha'] >= 120) {
                $transparent_count++;
                continue;
            }

            $reds[] = $color['red'];
            $greens[] = $color['green'];
            $blues[] = $color['blue'];
        }

        if (($mime_type === 'image/png' || $mime_type === 'image/webp') && $transparent_count >= 7) {
            return true;
        }

        if (count($reds) < 4) {
            return false;
        }

        $red_range = max($reds) - min($reds);
        $green_range = max($greens) - min($greens);
        $blue_range = max($blues) - min($blues);

        return ($red_range + $green_range + $blue_range) <= 3;
    }

    /**
     * Create a composition canvas with the correct alpha settings for each mime type.
     */
    private function createWorkingCanvas(int $width, int $height, string $mime_type) {
        $canvas = imagecreatetruecolor($width, $height);

        if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
            return $canvas;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $background);

        return $canvas;
    }

    /**
     * Copy a decoded source image onto the working canvas without losing opaque pixels.
     */
    private function copyImageOntoCanvas($canvas, $source, int $width, int $height, string $mime_type): void {
        if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
            imagealphablending($canvas, true);
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            return;
        }

        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
    }

    /**
     * Normalize loaded resources before composition.
     */
    private function normalizeImageResource($im, string $mime_type): void {
        if (function_exists('imagepalettetotruecolor') && !imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }

        if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
            imagealphablending($im, false);
            imagesavealpha($im, true);
        }
    }
    
    /**
     * JPEG/WebP 有损输出质量（与站点 `jpeg_quality` 过滤器对齐后再限制范围）
     */
    private function getOutputJpegWebpQuality(): int {
        $q = isset($this->options['output_jpeg_webp_quality'])
            ? (int) $this->options['output_jpeg_webp_quality']
            : 82;
        $q = max(40, min(100, $q));
        $filtered = (int) apply_filters('jpeg_quality', $q);
        return max(40, min(100, $filtered));
    }

    /**
     * PNG zlib 压缩级别 0–9（无损，仅影响体积与编码耗时）
     */
    private function getPngCompressionLevel(): int {
        $level = isset($this->options['output_png_compression'])
            ? (int) $this->options['output_png_compression']
            : 6;
        $level = max(0, min(9, $level));
        return max(0, min(9, (int) apply_filters('wpwatermark_png_compression', $level)));
    }

    /**
     * 水印后允许的最大体积增长比例，默认最多增长 20%。
     */
    private function getMaxOutputSizeGrowthRatio(): float {
        $ratio = (float) apply_filters('wpwatermark_max_size_growth_ratio', 1.2);
        return max(1.0, min(5.0, $ratio));
    }

    /**
     * 自适应重编码时的最低 JPEG/WebP 质量，避免为了体积过度损伤画质。
     */
    private function getMinAdaptiveJpegWebpQuality(): int {
        $quality = (int) apply_filters('wpwatermark_min_adaptive_jpeg_webp_quality', 76);
        return max(40, min(100, $quality));
    }

    /**
     * Helper function to save image
     * 
     * @param resource $im
     * @param string $filename
     * @param string $mime_type
     * @param string|null $source_filename
     * @return bool
     */
    private function saveImage($im, string $filename, string $mime_type, ?string $source_filename = null): bool {
        $target = $filename;
        $temp_file = null;
        if ($source_filename) {
            clearstatcache(true, $source_filename);
        }
        $source_size = ($source_filename && file_exists($source_filename)) ? filesize($source_filename) : false;

        if ($source_size !== false) {
            $temp_file = tempnam(dirname($filename), 'wpwatermark-');
            if ($temp_file) {
                $target = $temp_file;
            }
        }

        $saved = $this->writeImage($im, $target, $mime_type);
        if (!$saved) {
            if ($temp_file && file_exists($temp_file)) {
                @unlink($temp_file);
            }
            return false;
        }

        if ($source_size !== false) {
            clearstatcache(true, $target);
            $this->optimizeOutputSize($im, $target, $mime_type, (int) $source_size);
        }

        if (!$temp_file) {
            return true;
        }

        $copied = @copy($temp_file, $filename);
        @unlink($temp_file);
        return $copied;
    }

    /**
     * Write an image using configured quality/compression values.
     */
    private function writeImage($im, string $filename, string $mime_type, ?int $quality = null, ?int $png_compression = null): bool {
        switch ($mime_type) {
            case 'image/jpeg':
                return imagejpeg($im, $filename, $quality ?? $this->getOutputJpegWebpQuality());
            case 'image/png':
                // 第三参数为压缩级别：0 无压缩体积极大；PNG 无损，提高级别不损画质
                return imagepng($im, $filename, $png_compression ?? $this->getPngCompressionLevel());
            case 'image/gif':
                return imagegif($im, $filename);
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    return imagewebp($im, $filename, $quality ?? $this->getOutputJpegWebpQuality());
                }
                return false;
            default:
                return false;
        }
    }

    /**
     * Re-encode oversized outputs within conservative quality limits.
     */
    private function optimizeOutputSize($im, string $filename, string $mime_type, int $source_size): void {
        if ($source_size <= 0 || !file_exists($filename)) {
            return;
        }

        $max_size = (int) ceil($source_size * $this->getMaxOutputSizeGrowthRatio());
        clearstatcache(true, $filename);
        if (filesize($filename) <= $max_size) {
            return;
        }

        if ($mime_type === 'image/png') {
            if ($this->getPngCompressionLevel() < 9) {
                $this->writeImage($im, $filename, $mime_type, null, 9);
            }
            return;
        }

        if ($mime_type !== 'image/jpeg' && $mime_type !== 'image/webp') {
            return;
        }

        $configured_quality = $this->getOutputJpegWebpQuality();
        $min_quality = min($configured_quality, $this->getMinAdaptiveJpegWebpQuality());

        for ($quality = $configured_quality - 4; $quality >= $min_quality; $quality -= 4) {
            $this->writeImage($im, $filename, $mime_type, $quality);
            clearstatcache(true, $filename);
            if (file_exists($filename) && filesize($filename) <= $max_size) {
                return;
            }
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

                // 按 Porter-Duff over 合成，避免边缘泛白/发灰
                $src_opacity = (1 - ($src_color['alpha'] / 127)) * ($pct / 100);
                $dst_opacity = 1 - ($dst_color['alpha'] / 127);
                $out_opacity = $src_opacity + $dst_opacity * (1 - $src_opacity);

                if ($out_opacity <= 0) {
                    continue;
                }

                $final_red = (($src_color['red'] * $src_opacity) + ($dst_color['red'] * $dst_opacity * (1 - $src_opacity))) / $out_opacity;
                $final_green = (($src_color['green'] * $src_opacity) + ($dst_color['green'] * $dst_opacity * (1 - $src_opacity))) / $out_opacity;
                $final_blue = (($src_color['blue'] * $src_opacity) + ($dst_color['blue'] * $dst_opacity * (1 - $src_opacity))) / $out_opacity;
                $final_alpha = 127 - intval(round($out_opacity * 127));
                
                // 创建新颜色
                $final_color = imagecolorallocatealpha(
                    $dst_im,
                    intval(round($final_red)),
                    intval(round($final_green)),
                    intval(round($final_blue)),
                    min(127, max(0, intval($final_alpha)))
                );
                
                // 设置像素
                imagesetpixel($dst_im, $dst_x + $x, $dst_y + $y, $final_color);
            }
        }
    }

    /**
     * Resize watermark image while preserving alpha channel.
     */
    private function resizeWatermarkResource($source, $source_width, $source_height, $target_width, $target_height) {
        if ($target_width <= 0 || $target_height <= 0) {
            return false;
        }

        $target = imagecreatetruecolor($target_width, $target_height);
        imagealphablending($target, false);
        imagesavealpha($target, true);

        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $target_width, $target_height, $transparent);

        $success = imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $target_width,
            $target_height,
            $source_width,
            $source_height
        );

        if (!$success) {
            imagedestroy($target);
            return false;
        }

        imagealphablending($target, true);
        return $target;
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
    private function calculatePosition($position, $img_width, $img_height, $text = '', $mark_width = 0, $mark_height = 0, $font_size = null, $font_angle = null, $font_file = null) {
        $margin = intval($this->options['watermark_margin']);
        
        // For text watermark
        if ($text !== '') {
            $font_size = $font_size === null ? intval($this->options['text_size']) : intval($font_size);
            $font_angle = $font_angle === null ? intval($this->options['text_angle']) : intval($font_angle);
            $font_file = $font_file === null ? $this->font_dir . $this->options['text_font'] : $font_file;
            $text_box = imagettfbbox($font_size, $font_angle, $font_file, $text);
            $mark_width = abs($text_box[2] - $text_box[0]);
            $mark_height = abs($text_box[1] - $text_box[7]);
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

    /**
     * Render text on a transparent layer first to avoid edge artifacts.
     */
    private function renderTextLayer($base_image, $width, $height, $text, $font_file, $font_size, $font_angle, $x, $y, $rgb, $opacity) {
        $layer = imagecreatetruecolor($width, $height);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);

        $transparent = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        imagefilledrectangle($layer, 0, 0, $width, $height, $transparent);

        imagealphablending($layer, true);
        $alpha = intval(round((100 - $opacity) * 127 / 100)); // 0(不透明)-127(全透明)
        $text_color = imagecolorallocatealpha($layer, $rgb['r'], $rgb['g'], $rgb['b'], $alpha);

        imagettftext(
            $layer,
            $font_size,
            $font_angle,
            $x,
            $y,
            $text_color,
            $font_file,
            $text
        );

        imagealphablending($base_image, true);
        imagecopy($base_image, $layer, 0, 0, 0, 0, $width, $height);
        imagedestroy($layer);
    }
} 