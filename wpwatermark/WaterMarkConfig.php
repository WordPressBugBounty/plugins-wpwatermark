<?php
/**
 * WaterMark Configuration Class
 * 
 * Manages watermark settings with validation and defaults
 */
class WaterMarkConfig {
    // Default settings
    private static $defaults = [
        'watermark_enabled' => '0',
        'watermark_type' => 'text_watermark',
        'text_content' => '',
        'text_font' => 'simhei.ttf',
        'text_angle' => '0',
        'text_size' => '14',
        'text_color' => '#790000',
        'watermark_mark_image' => '',
        'watermark_position' => 'bottom-right',
        'watermark_margin' => '50',
        'watermark_diaphaneity' => '100',
        'watermark_min_width' => '300',
        'watermark_min_height' => '300',
        'watermark_extension_whitelist' => ''
    ];
    
    private $options;
    
    /**
     * Constructor
     * 
     * @param array $options Optional custom options
     */
    public function __construct($options = []) {
        $this->options = wp_parse_args($options, self::$defaults);
        $this->validateOptions();
    }
    
    /**
     * Validate all options
     */
    private function validateOptions() {
        // Validate watermark type
        if (!in_array($this->options['watermark_type'], ['text_watermark', 'image_watermark'])) {
            $this->options['watermark_type'] = self::$defaults['watermark_type'];
        }
        
        // Validate numeric values
        $this->options['text_angle'] = $this->validateNumeric('text_angle', 0, 360);
        $this->options['text_size'] = $this->validateNumeric('text_size', 8, 72);
        $this->options['watermark_margin'] = $this->validateNumeric('watermark_margin', 0, 200);
        $this->options['watermark_diaphaneity'] = $this->validateNumeric('watermark_diaphaneity', 0, 100);
        $this->options['watermark_min_width'] = $this->validateNumeric('watermark_min_width', 100, 9999);
        $this->options['watermark_min_height'] = $this->validateNumeric('watermark_min_height', 100, 9999);
        
        // Validate watermark position（九宫格固定位置 + random 随机一格）
        $valid_positions = [
            'random',
            'top-left', 'top-center', 'top-right',
            'middle-left', 'middle-center', 'middle-right',
            'bottom-left', 'bottom-center', 'bottom-right'
        ];
        if (!in_array($this->options['watermark_position'], $valid_positions, true)) {
            $this->options['watermark_position'] = self::$defaults['watermark_position'];
        }
        
        // Validate color
        $this->options['text_color'] = $this->validateColor($this->options['text_color']);
        
        // Validate text content
        $this->options['text_content'] = sanitize_text_field($this->options['text_content']);
        
        // Validate font file
        if (!$this->validateFont($this->options['text_font'])) {
            $this->options['text_font'] = self::$defaults['text_font'];
        }
        
        // Validate image URL if using image watermark
        if ($this->options['watermark_type'] === 'image_watermark') {
            $this->options['watermark_mark_image'] = $this->validateImageUrl($this->options['watermark_mark_image']);
        }

        // Validate extension whitelist
        $this->options['watermark_extension_whitelist'] = $this->normalizeExtensionList(
            $this->options['watermark_extension_whitelist']
        );
    }
    
    /**
     * Validate numeric value
     */
    private function validateNumeric($key, $min, $max) {
        $value = intval($this->options[$key]);
        return max($min, min($max, $value));
    }
    
    /**
     * Validate color value
     */
    private function validateColor($color) {
        if (preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
            return $color;
        }
        return self::$defaults['text_color'];
    }
    
    /**
     * Validate font file
     */
    private function validateFont($font) {
        $font_path = plugin_dir_path(__FILE__) . 'fonts/' . $font;
        return file_exists($font_path) && is_readable($font_path);
    }
    
    /**
     * Validate image URL
     */
    private function validateImageUrl($url) {
        if (empty($url)) {
            return '';
        }
        
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        
        // Check if image exists and is accessible
        $headers = get_headers($url, 1);
        if (strpos($headers[0], '200') === false || 
            !preg_match('/^image\/(jpeg|png|gif|webp)/', $headers['Content-Type'])) {
            return '';
        }
        
        return esc_url_raw($url);
    }

    /**
     * Normalize extension whitelist to lowercase CSV
     */
    private function normalizeExtensionList($extensions) {
        $extensions = is_string($extensions) ? strtolower($extensions) : '';
        $extensions = explode(',', $extensions);
        $normalized = [];

        foreach ($extensions as $ext) {
            $ext = trim($ext);
            $ext = ltrim($ext, '.');
            if ($ext === '') {
                continue;
            }
            if (preg_match('/^[a-z0-9]+$/', $ext)) {
                $normalized[] = $ext;
            }
        }

        $normalized = array_values(array_unique($normalized));
        return implode(',', $normalized);
    }
    
    /**
     * Get all options
     */
    public function getOptions() {
        return $this->options;
    }
    
    /**
     * Get single option
     */
    public function getOption($key) {
        return isset($this->options[$key]) ? $this->options[$key] : null;
    }
    
    /**
     * Set single option
     */
    public function setOption($key, $value) {
        if (array_key_exists($key, self::$defaults)) {
            $this->options[$key] = $value;
            $this->validateOptions();
        }
    }
    
    /**
     * Save options to WordPress
     */
    public function saveOptions() {
        return update_option('wpwatermark_options', $this->options);
    }
    
    /**
     * Load options from WordPress
     */
    public static function loadFromWordPress() {
        $options = get_option('wpwatermark_options', []);
        return new self($options);
    }
} 