<?php
/**
 * 插件设置页面
 *
 * @package WPWaterMark
 * @version 5.1.2
 */
// require_once('WaterMarkFunctions.php');

if (!defined('ABSPATH')) {
	exit;
}

// 确保 WPWaterMark_VERSION 常量可用
if (!defined('WPWaterMark_VERSION')) {
	define('WPWaterMark_VERSION', '5.0.0');
}

function wpwatermark_setting_page() {
	global $wpwatermark_options;
	
	// 如果没有全局变量，尝试获取选项
	if (!isset($wpwatermark_options) || !is_array($wpwatermark_options)) {
		$wpwatermark_options = get_option('wpwatermark_options', array());
	}
	
	// 确保选项是数组
	if (!is_array($wpwatermark_options)) {
		$config = new WaterMarkConfig();
		$wpwatermark_options = $config->getOptions();
	}
	
	// 处理表单提交
	if (isset($_POST['submit']) && check_admin_referer('wpwatermark_settings')) {
		// 更新选项
		$wpwatermark_options['watermark_enabled'] = isset($_POST['watermark_enabled']) ? '1' : '0';
		$wpwatermark_options['watermark_type'] = sanitize_text_field($_POST['watermark_type'] ?? 'text_watermark');
		$wpwatermark_options['text_content'] = sanitize_text_field($_POST['text_content'] ?? '');
		$wpwatermark_options['text_font'] = sanitize_text_field($_POST['text_font'] ?? 'simhei.ttf');
		$wpwatermark_options['text_angle'] = absint($_POST['text_angle'] ?? 0);
		$wpwatermark_options['text_size'] = absint($_POST['text_size'] ?? 14);
		$wpwatermark_options['text_color'] = sanitize_hex_color($_POST['text_color'] ?? '#790000');
		$wpwatermark_options['watermark_mark_image'] = esc_url_raw($_POST['watermark_mark_image'] ?? '');
		$wpwatermark_options['watermark_position'] = sanitize_text_field($_POST['watermark_position'] ?? 'bottom-right');
		$wpwatermark_options['watermark_margin'] = absint($_POST['watermark_margin'] ?? 50);
		$wpwatermark_options['watermark_diaphaneity'] = absint($_POST['watermark_diaphaneity'] ?? 100);
		$wpwatermark_options['watermark_min_width'] = absint($_POST['watermark_min_width'] ?? 300);
		$wpwatermark_options['watermark_min_height'] = absint($_POST['watermark_min_height'] ?? 300);
		
		update_option('wpwatermark_options', $wpwatermark_options);
		echo '<div class="notice notice-success is-dismissible"><p><strong>' . 
			 __('设置已保存。', 'wpwatermark') . 
			 '</strong></p></div>';
	}
	
	// 处理预览
	if (isset($_POST['preview']) && check_admin_referer('wpwatermark_settings')) {
		$handler = new WaterMarkHandler($wpwatermark_options);
		$demo_img_path = plugin_dir_path(__FILE__);
		$im_url = $demo_img_path . 'demo.jpg';
		$new_im_url = $demo_img_path . 'preview.jpg';
		
		if ($wpwatermark_options['watermark_type'] === 'text_watermark') {
			$handler->createTextWatermark(
				$im_url,
				$new_im_url,
				$wpwatermark_options['text_content'],
				$wpwatermark_options
			);
		} elseif ($wpwatermark_options['watermark_type'] === 'image_watermark') {
			$handler->createImageWatermark(
				$im_url,
				$wpwatermark_options['watermark_mark_image'],
				$new_im_url,
				$wpwatermark_options
			);
		}
	}
	
	// 加载WordPress的颜色选择器
	wp_enqueue_style('wp-color-picker');
	wp_enqueue_script('wp-color-picker');
	
	// 加载媒体上传器
	wp_enqueue_media();
	
	// 添加自定义样式和脚本
	wp_enqueue_style('wpwatermark-admin', plugin_dir_url(__FILE__) . 'css/admin.css', array(), WPWaterMark_VERSION);
	wp_enqueue_script('wpwatermark-admin', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery', 'wp-color-picker'), WPWaterMark_VERSION, true);
	
	// 输出设置页面HTML
	?>
	<div class="wrap wpwatermark-wrap">
		<h1>WPWaterMark 水印插件设置</h1>
		<p>在这里，我们要对水印插件设置。<a href="https://www.lezaiyun.com/792.html" target="_blank">插件介绍</a>（关注公众号：<span style="color: red;">老蒋朋友圈</span>）</p>
		<form method="post" action="" class="wpwatermark-form">
			<?php wp_nonce_field('wpwatermark_settings'); ?>
			
			<table class="form-table">
				<tr>
					<th scope="row">启用水印</th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="watermark_enabled" value="1" <?php checked($wpwatermark_options['watermark_enabled'], '1'); ?>>
								启用水印功能
							</label>
							<p class="description">勾选此项后，水印功能才会生效</p>
						</fieldset>
					</td>
				</tr>
				
				<tr>
					<th scope="row">水印类型</th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="watermark_type" value="text_watermark" <?php checked($wpwatermark_options['watermark_type'], 'text_watermark'); ?>>
								文字水印
							</label>
							<br>
							<label>
								<input type="radio" name="watermark_type" value="image_watermark" <?php checked($wpwatermark_options['watermark_type'], 'image_watermark'); ?>>
								图片水印（推荐）
							</label>
						</fieldset>
					</td>
				</tr>
				
				<tr class="text-watermark-options" <?php echo $wpwatermark_options['watermark_type'] !== 'text_watermark' ? 'style="display:none;"' : ''; ?>>
					<th scope="row">水印文字</th>
					<td>
						<input type="text" name="text_content" value="<?php echo esc_attr($wpwatermark_options['text_content']); ?>" class="regular-text">
					</td>
				</tr>
				
				<tr class="text-watermark-options" <?php echo $wpwatermark_options['watermark_type'] !== 'text_watermark' ? 'style="display:none;"' : ''; ?>>
					<th scope="row">字体</th>
					<td>
						<select name="text_font">
							<?php
							$fonts_dir = plugin_dir_path(__FILE__) . 'fonts/';
							$fonts = scandir($fonts_dir);
							foreach ($fonts as $font) {
								if ($font != "." && $font != "..") {
									echo '<option value="' . esc_attr($font) . '" ' . selected($wpwatermark_options['text_font'], $font, false) . '>' . esc_html($font) . '</option>';
								}
							}
							?>
						</select>
						<p class="description">可以将字体ttf文件放在fonts文件夹中，然后选择字体。</p>
					</td>
				</tr>
				
				<tr class="text-watermark-options" <?php echo $wpwatermark_options['watermark_type'] !== 'text_watermark' ? 'style="display:none;"' : ''; ?>>
					<th scope="row">字体大小</th>
					<td>
						<input type="number" name="text_size" value="<?php echo esc_attr($wpwatermark_options['text_size']); ?>" class="small-text" min="1" max="100">
						<p class="description">像素大小</p>
					</td>
				</tr>
				
				<tr class="text-watermark-options" <?php echo $wpwatermark_options['watermark_type'] !== 'text_watermark' ? 'style="display:none;"' : ''; ?>>
					<th scope="row">字体颜色</th>
					<td>
						<input type="text" name="text_color" value="<?php echo esc_attr($wpwatermark_options['text_color']); ?>" class="wpwatermark-color-picker">
					</td>
				</tr>
				
				<tr class="text-watermark-options" <?php echo $wpwatermark_options['watermark_type'] !== 'text_watermark' ? 'style="display:none;"' : ''; ?>>
					<th scope="row">文字角度</th>
					<td>
						<input type="number" name="text_angle" value="<?php echo esc_attr($wpwatermark_options['text_angle']); ?>" class="small-text" min="0" max="360">
						<p class="description">0-360度之间</p>
					</td>
				</tr>
				
				<tr class="image-watermark-options" <?php echo $wpwatermark_options['watermark_type'] !== 'image_watermark' ? 'style="display:none;"' : ''; ?>>
					<th scope="row">水印图片</th>
					<td>
						<div class="wpwatermark-media-preview">
							<?php if (!empty($wpwatermark_options['watermark_mark_image'])): ?>
								<img src="<?php echo esc_url($wpwatermark_options['watermark_mark_image']); ?>" alt="水印预览">
							<?php endif; ?>
						</div>
						<input type="hidden" name="watermark_mark_image" id="watermark_mark_image" value="<?php echo esc_attr($wpwatermark_options['watermark_mark_image']); ?>">
						<button type="button" class="button wpwatermark-upload-button">选择图片</button>
					</td>
				</tr>
				
				<tr>
					<th scope="row">水印位置</th>
					<td>
						<div class="wpwatermark-position-selector">
							<?php
							$positions = array(
								'top-left' => '左上',
								'top-center' => '中上',
								'top-right' => '右上',
								'middle-left' => '左中',
								'middle-center' => '中心',
								'middle-right' => '右中',
								'bottom-left' => '左下',
								'bottom-center' => '中下',
								'bottom-right' => '右下'
							);
							
							foreach ($positions as $value => $label) {
								echo '<button type="button" data-position="' . esc_attr($value) . '" ' . 
									 ($wpwatermark_options['watermark_position'] === $value ? 'class="active"' : '') . '>' . 
									 esc_html($label) . '</button>';
							}
							?>
						</div>
						<input type="hidden" name="watermark_position" id="watermark_position" value="<?php echo esc_attr($wpwatermark_options['watermark_position']); ?>">
					</td>
				</tr>
				
				<tr>
					<th scope="row">边距</th>
					<td>
						<input type="number" name="watermark_margin" value="<?php echo esc_attr($wpwatermark_options['watermark_margin']); ?>" class="small-text" min="0">
						<p class="description">水印距离边缘的距离（像素）</p>
					</td>
				</tr>
				
				<tr class="image-watermark-options" <?php echo $wpwatermark_options['watermark_type'] !== 'image_watermark' ? 'style="display:none;"' : ''; ?>>
					<th scope="row">透明度</th>
					<td>
						<input type="number" name="watermark_diaphaneity" value="<?php echo esc_attr($wpwatermark_options['watermark_diaphaneity']); ?>" class="small-text" min="0" max="100">
						<p class="description">0-100之间，100为完全不透明</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">最小尺寸限制</th>
					<td>
						<label>
							宽度：
							<input type="number" name="watermark_min_width" value="<?php echo esc_attr($wpwatermark_options['watermark_min_width']); ?>" class="small-text" min="0">
						</label>
						<label>
							高度：
							<input type="number" name="watermark_min_height" value="<?php echo esc_attr($wpwatermark_options['watermark_min_height']); ?>" class="small-text" min="0">
						</label>
						<p class="description">只有超过这个尺寸的图片才会添加水印</p>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="保存更改">
				<input type="submit" name="preview" class="button" value="预览效果">
			</p>
		</form>
		<p><img width="150" height="150" src="<?php echo plugins_url('/images/wechat.png', __FILE__); ?>" alt="扫码关注公众号" /></p>
		<?php if (isset($_POST['preview']) && file_exists(plugin_dir_path(__FILE__) . 'preview.jpg')): ?>
		<div class="wpwatermark-preview">
			<h2>预览效果</h2>
			<img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'preview.jpg?' . time()); ?>" alt="水印预览">
		</div>
		<?php endif; ?>
	</div>
	<?php
}
?>