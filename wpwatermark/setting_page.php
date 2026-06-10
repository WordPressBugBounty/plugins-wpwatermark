<?php
/**
 * 插件设置页面
 *
 * @package WPWaterMark
 * @version 5.2.4
 */
// require_once('WaterMarkFunctions.php');

if (!defined('ABSPATH')) {
	exit;
}

// 确保 WPWaterMark_VERSION 常量可用
if (!defined('WPWaterMark_VERSION')) {
	define('WPWaterMark_VERSION', '5.2.4');
}

function wpwatermark_setting_page() {
	global $wpwatermark_options;
	
	// 如果没有全局变量，尝试获取选项
	if (!isset($wpwatermark_options) || !is_array($wpwatermark_options)) {
		$wpwatermark_options = get_option('wpwatermark_options', array());
	}
	
	if (!is_array($wpwatermark_options)) {
		$wpwatermark_options = array();
	}
	$wpwatermark_options = (new WaterMarkConfig($wpwatermark_options))->getOptions();
	
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
		$wpwatermark_options['image_watermark_scale'] = absint($_POST['image_watermark_scale'] ?? 100);
		// 位置：优先根据单选项（与隐藏域双保险，避免仅依赖 JS 时未提交 random）
		$position_mode = isset($_POST['wpwatermark_position_mode'])
			? sanitize_text_field(wp_unslash($_POST['wpwatermark_position_mode']))
			: 'fixed';
		if ($position_mode === 'random') {
			$wpwatermark_options['watermark_position'] = 'random';
		} else {
			$pos = isset($_POST['watermark_position']) ? sanitize_text_field(wp_unslash($_POST['watermark_position'])) : 'bottom-right';
			$wpwatermark_options['watermark_position'] = ($pos === 'random') ? 'bottom-right' : $pos;
		}
		$wpwatermark_options['watermark_margin'] = absint($_POST['watermark_margin'] ?? 50);
		$wpwatermark_options['watermark_diaphaneity'] = absint($_POST['watermark_diaphaneity'] ?? 100);
		$wpwatermark_options['watermark_min_width'] = absint($_POST['watermark_min_width'] ?? 300);
		$wpwatermark_options['watermark_min_height'] = absint($_POST['watermark_min_height'] ?? 300);
		$wpwatermark_options['watermark_extension_whitelist'] = sanitize_text_field(
			wp_unslash($_POST['watermark_extension_whitelist'] ?? '')
		);
		$wpwatermark_options['output_jpeg_webp_quality'] = max(
			40,
			min(100, absint($_POST['output_jpeg_webp_quality'] ?? 82))
		);
		$wpwatermark_options['output_png_compression'] = max(
			0,
			min(9, absint($_POST['output_png_compression'] ?? 6))
		);
		
		$wpwatermark_options = (new WaterMarkConfig($wpwatermark_options))->getOptions();
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
		<p>在这里，我们要对水印插件设置。<a href="https://www.lezaiyun.com/wpwatermark.html" target="_blank">插件介绍</a>（关注公众号：<span style="color: red;">lezaiyun</span>）</p>
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

				<tr class="image-watermark-options" <?php echo $wpwatermark_options['watermark_type'] !== 'image_watermark' ? 'style="display:none;"' : ''; ?>>
					<th scope="row">图片水印缩放比例</th>
					<td>
						<input type="number" name="image_watermark_scale" value="<?php echo esc_attr($wpwatermark_options['image_watermark_scale'] ?? 100); ?>" class="small-text" min="1" max="100">
						<p class="description">仅对图片水印生效。100 为原始大小，建议 20-80。按原图等比例缩小，不会拉伸变形。</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">水印位置</th>
					<td>
						<?php
						$position_is_random = isset($wpwatermark_options['watermark_position']) && $wpwatermark_options['watermark_position'] === 'random';
						?>
						<fieldset class="wpwatermark-position-mode" style="margin-bottom:12px;">
							<label style="display:inline-block;margin-right:16px;">
								<input type="radio" name="wpwatermark_position_mode" value="fixed" <?php checked(!$position_is_random); ?>>
								固定九宫格
							</label>
							<label style="display:inline-block;">
								<input type="radio" name="wpwatermark_position_mode" value="random" <?php checked($position_is_random); ?>>
								随机九宫格（每次上传在九宫格中随机一格）
							</label>
						</fieldset>
						<div class="wpwatermark-position-selector"<?php echo $position_is_random ? ' style="opacity:0.5;"' : ''; ?>>
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
									(!$position_is_random && isset($wpwatermark_options['watermark_position']) && $wpwatermark_options['watermark_position'] === $value ? 'class="active"' : '') . '>' .
									esc_html($label) . '</button>';
							}
							?>
						</div>
						<input type="hidden" name="watermark_position" id="watermark_position" value="<?php echo esc_attr($wpwatermark_options['watermark_position']); ?>">
						<p class="description">固定九宫格：水印始终在所选格子内；随机九宫格：每张图处理时从九个格子中随机选一个（与固定模式二选一）。</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">边距</th>
					<td>
						<input type="number" name="watermark_margin" value="<?php echo esc_attr($wpwatermark_options['watermark_margin']); ?>" class="small-text" min="0">
						<p class="description">水印距离边缘的距离（像素）</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">透明度</th>
					<td>
						<input type="number" name="watermark_diaphaneity" value="<?php echo esc_attr($wpwatermark_options['watermark_diaphaneity']); ?>" class="small-text" min="0" max="100">
						<p class="description">0-100之间，100为完全不透明。此设置同时对文字水印和图片水印生效。</p>
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

				<tr>
					<th scope="row">输出体积优化</th>
					<td>
						<label>
							JPEG / WebP 质量（40–100）：
							<input type="number" name="output_jpeg_webp_quality" value="<?php echo esc_attr($wpwatermark_options['output_jpeg_webp_quality']); ?>" class="small-text" min="40" max="100" step="1">
						</label>
						<p class="description">默认 82，与 WordPress 常见设置接近；数值越低文件越小（有损略增）。若水印后体积超过原图约 20%，会在保守范围内自动降档重试。站点上的 <code>jpeg_quality</code> 过滤器仍会参与计算。</p>
						<label>
							PNG 压缩级别（0–9，无损）：
							<input type="number" name="output_png_compression" value="<?php echo esc_attr($wpwatermark_options['output_png_compression']); ?>" class="small-text" min="0" max="9" step="1">
						</label>
						<p class="description">此前使用 0 表示「无 zlib 压缩」，PNG 会异常偏大；默认 6 在体积与编码速度之间较均衡。若水印后体积偏大，会自动改用 9 级无损压缩重试，且<strong>不改变像素画质</strong>。</p>
					</td>
				</tr>

				<tr>
					<th scope="row">水印白名单后缀</th>
					<td>
						<input type="text" name="watermark_extension_whitelist" value="<?php echo esc_attr($wpwatermark_options['watermark_extension_whitelist'] ?? ''); ?>" class="regular-text" placeholder="例如：webp,png">
						<?php
						$supported_extensions = WaterMarkHandler::getSupportedExtensions();
						$webp_supported = in_array('webp', $supported_extensions, true);
						?>
						<p class="description">使用英文逗号分隔，填写后这些后缀上传时不添加水印（例如：webp,png）。</p>
						<p class="description">当前支持添加水印的后缀：<?php echo esc_html(implode(', ', $supported_extensions)); ?><?php echo $webp_supported ? '' : '（当前环境未启用 WebP 处理能力）'; ?>。</p>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="保存更改">
				<input type="submit" name="preview" class="button" value="预览效果">
			</p>
		</form>
		<?php if (isset($_POST['preview']) && file_exists(plugin_dir_path(__FILE__) . 'preview.jpg')): ?>
		<div class="wpwatermark-preview">
			<h2>预览效果</h2>
			<img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'preview.jpg?' . time()); ?>" alt="水印预览">
		</div>
		<?php endif; ?>
		<div class="wpwatermark-footer">
			<img
				class="wpwatermark-footer__qrcode"
				src="<?php echo esc_url(plugins_url('images/wechat.png', __FILE__)); ?>"
				width="150"
				height="150"
				alt="乐在云公众号二维码"
			>
			<p class="wpwatermark-footer__desc">关注乐在云，获取插件教程、更新通知</p>
			<p class="wpwatermark-footer__copyright">2026 &copy;
				<a href="https://www.lezaiyun.com/" target="_blank" rel="noopener noreferrer">乐在云工作室</a>
				<span class="wpwatermark-footer__sep">·</span>
				<span>By </span><a href="https://www.laojiang.me/" target="_blank" rel="noopener noreferrer">老蒋</a>
			</p>
		</div>
	</div>
	<?php
}
?>