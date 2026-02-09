<?php
/**
 * Plugin Name: WPWaterMark
 * Plugin URI: https://www.lezaiyun.com/792.html
 * Description: WordPress轻水印插件，支持文字水印和图片水印，支持批量添加水印，支持自定义水印位置、大小、颜色、透明度等。公众号：老蒋朋友圈
 * Version: 5.1.4
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: 老蒋和他的伙伴们
 * Author URI: https://www.lezaiyun.com
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpwatermark
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 检查PHP版本要求
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . 
             sprintf(__('WPWaterMark 插件需要 PHP %s 或更高版本。您当前的PHP版本是 %s，请升级您的PHP版本。', 'wpwatermark'), 
                     '7.4', PHP_VERSION) . 
             '</p></div>';
    });
    return;
}

// 定义插件版本和路径常量
define('WPWaterMark_VERSION', '5.1.2');
define('WPWaterMark_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPWaterMark_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPWaterMark_BASENAME', plugin_basename(__FILE__));

// 加载必要的类
require_once(WPWaterMark_PLUGIN_DIR . 'WaterMarkConfig.php');
require_once(WPWaterMark_PLUGIN_DIR . 'WaterMarkHandler.php');
require_once(WPWaterMark_PLUGIN_DIR . 'WaterMarkPerformance.php');

class WPWaterMark {
    private $config;
    private $handler;
    private $performance;
    
    /**
     * 构造函数
     */
    public function __construct() {
        // 初始化组件
        $this->config = WaterMarkConfig::loadFromWordPress();
        $this->handler = new WaterMarkHandler($this->config->getOptions());
        $this->performance = new WaterMarkPerformance();
        
        // 注册钩子
        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('admin_init', array($this, 'registerSettings'));
        add_filter('wp_handle_upload', array($this, 'handleImageUpload'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));
        
        // 添加定期清理日志的计划任务
        if (!wp_next_scheduled('wpwatermark_clean_logs')) {
            wp_schedule_event(time(), 'daily', 'wpwatermark_clean_logs');
        }
        add_action('wpwatermark_clean_logs', array($this, 'cleanLogs'));
    }
    
    /**
     * 加载管理界面所需的脚本和样式
     */
    public function enqueueAdminScripts($hook) {
        if ($hook != 'settings_page_wpwatermark') {
            return;
        }
        
        // 加载WordPress原生的媒体上传器
        wp_enqueue_media();
        
        // 加载WordPress原生的颜色选择器
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // 加载自定义脚本和样式
        wp_enqueue_style(
            'wpwatermark-admin',
            WPWaterMark_PLUGIN_URL . 'css/admin.css',
            array(),
            WPWaterMark_VERSION
        );
        
        wp_enqueue_script(
            'wpwatermark-admin',
            WPWaterMark_PLUGIN_URL . 'js/admin.js',
            array('jquery', 'wp-color-picker'),
            WPWaterMark_VERSION,
            true
        );
    }
    
    /**
     * 添加管理菜单
     */
    public function addAdminMenu() {
        add_options_page(
            'WPWaterMark设置',
            'WPWaterMark',
            'manage_options',
            'wpwatermark',
            array($this, 'displaySettingsPage')
        );
    }
    
    /**
     * 注册设置
     */
    public function registerSettings() {
        register_setting('wpwatermark_options', 'wpwatermark_options', array($this, 'validateSettings'));
    }
    
    /**
     * 验证设置
     */
    public function validateSettings($input) {
        $config = new WaterMarkConfig($input);
        return $config->getOptions();
    }
    
    /**
     * 处理图片上传
     */
    public function handleImageUpload($file) {
        // 检查是否为图片
        if (strpos($file['type'], 'image') === false) {
            return $file;
        }
        
        // 检查水印功能是否启用
        if ($this->config->getOption('watermark_enabled') !== '1') {
            return $file;
        }
        
        // 获取图片信息
        $image_size = getimagesize($file['file']);
        if (!$image_size) {
            return $file;
        }
        
        // 检查图片尺寸是否满足要求
        if ($image_size[0] < $this->config->getOption('watermark_min_width') || 
            $image_size[1] < $this->config->getOption('watermark_min_height')) {
            return $file;
        }
        
        // 开始性能监控
        $this->performance->startMonitoring();
        
        try {
            // 添加水印
            if ($this->config->getOption('watermark_type') === 'text_watermark') {
                $this->handler->createTextWatermark(
                    $file['file'],
                    $file['file'],
                    $this->config->getOption('text_content')
                );
            } else {
                $this->handler->createImageWatermark(
                    $file['file'],
                    $this->config->getOption('watermark_mark_image'),
                    $file['file']
                );
            }
            
            // 记录性能数据
            $this->performance->endMonitoring('image_upload', [
                'file_size' => filesize($file['file']),
                'image_dimensions' => $image_size[0] . 'x' . $image_size[1]
            ]);
            
        } catch (Exception $e) {
            error_log('WPWaterMark Error: ' . $e->getMessage());
        }
        
        return $file;
    }
    
    /**
     * 显示设置页面
     */
    public function displaySettingsPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient privileges!', 'wpwatermark'));
        }
        
        // 确保选项存在
        $wpwatermark_options = get_option('wpwatermark_options');
        if (!is_array($wpwatermark_options)) {
            $config = new WaterMarkConfig();
            $wpwatermark_options = $config->getOptions();
            update_option('wpwatermark_options', $wpwatermark_options);
        }
        
        // 加载设置页面模板
        require_once(WPWaterMark_PLUGIN_DIR . 'setting_page.php');
        wpwatermark_setting_page();
    }
    
    /**
     * 清理日志
     */
    public function cleanLogs() {
        $this->performance->cleanOldLogs(30); // 保留30天的日志
    }
    
    /**
     * 插件激活时的处理
     */
    public static function activate() {
        // 创建必要的目录
        $dirs = array('cache', 'logs');
        foreach ($dirs as $dir) {
            $path = WPWaterMark_PLUGIN_DIR . $dir;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
        }
        
        // 设置默认选项
        if (!get_option('wpwatermark_options')) {
            $config = new WaterMarkConfig();
            update_option('wpwatermark_options', $config->getOptions());
        }
    }
    
    /**
     * 插件停用时的处理
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('wpwatermark_clean_logs');
    }
    
    /**
     * 插件卸载时的处理
     */
    public static function uninstall() {
        // 清理选项
        delete_option('wpwatermark_options');
        
        // 清理文件
        $dirs = array('cache', 'logs');
        foreach ($dirs as $dir) {
            $path = WPWaterMark_PLUGIN_DIR . $dir;
            if (file_exists($path)) {
                self::removeDirectory($path);
            }
        }
    }
    
    /**
     * 递归删除目录
     */
    private static function removeDirectory($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        self::removeDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}

// 注册激活、停用和卸载钩子
register_activation_hook(__FILE__, array('WPWaterMark', 'activate'));
register_deactivation_hook(__FILE__, array('WPWaterMark', 'deactivate'));
register_uninstall_hook(__FILE__, array('WPWaterMark', 'uninstall'));

// 初始化插件
$wpwatermark = new WPWaterMark();
