<?php
/**
 *  插件设置页面
 */
// require_once('WaterMarkFunctions.php');

function wpwatermark_setting_page() {
// 如果当前用户权限不足
	if (!current_user_can('manage_options')) {
		wp_die('Insufficient privileges!');
	}

	$wpwatermark_options = get_option('wpwatermark_options');
	if ($wpwatermark_options && !empty($_POST)) 
		$wpwatermark_options['watermark_type'] = (isset($_POST['watermark_type'])) ? sanitize_text_field(trim(stripslashes($_POST['watermark_type']))) : $wpwatermark_options['watermark_type'];
		$wpwatermark_options['text_content'] = (isset($_POST['text_content'])) ? sanitize_text_field(trim(stripslashes($_POST['text_content']))) : $wpwatermark_options['text_content'];
		$wpwatermark_options['text_font'] = (isset($_POST['text_font'])) ? sanitize_text_field(trim(stripslashes($_POST['text_font']))) : $wpwatermark_options['text_font'];
		$wpwatermark_options['text_angle'] = (isset($_POST['text_angle'])) ? sanitize_text_field(trim(stripslashes($_POST['text_angle']))) : $wpwatermark_options['text_angle'];
		$wpwatermark_options['text_size'] = (isset($_POST['text_size'])) ? sanitize_text_field(trim(stripslashes($_POST['text_size']))) : $wpwatermark_options['text_size'];
		$wpwatermark_options['text_color'] = (isset($_POST['text_color'])) ? sanitize_text_field(trim(stripslashes($_POST['text_color']))) : $wpwatermark_options['text_color'];
		$wpwatermark_options['watermark_mark_image'] = (isset($_POST['watermark_mark_image'])) ? sanitize_text_field(trim(stripslashes($_POST['watermark_mark_image']))) : $wpwatermark_options['watermark_mark_image'];
		$wpwatermark_options['watermark_position'] = (isset($_POST['watermark_position'])) ? sanitize_text_field(trim(stripslashes($_POST['watermark_position']))) : $wpwatermark_options['watermark_position'];
		$wpwatermark_options['watermark_margin'] = (isset($_POST['watermark_margin'])) ? sanitize_text_field(trim(stripslashes($_POST['watermark_margin']))) : $wpwatermark_options['watermark_margin'];
		$wpwatermark_options['watermark_diaphaneity'] = (isset($_POST['watermark_diaphaneity'])) ? sanitize_text_field(trim(stripslashes($_POST['watermark_diaphaneity']))) : $wpwatermark_options['watermark_diaphaneity'];
		$wpwatermark_options['watermark_spacing'] = (isset($_POST['watermark_spacing'])) ? sanitize_text_field(trim(stripslashes($_POST['watermark_spacing']))) : $wpwatermark_options['watermark_spacing'];
		$wpwatermark_options['watermark_min_width'] = (isset($_POST['watermark_min_width'])) ? sanitize_text_field(trim(stripslashes($_POST['watermark_min_width']))) : $wpwatermark_options['watermark_min_width'];
		$wpwatermark_options['watermark_min_height'] = (isset($_POST['watermark_min_height'])) ? sanitize_text_field(trim(stripslashes($_POST['watermark_min_height']))) : $wpwatermark_options['watermark_min_height'];

		if ( isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce']) ) {
			// 不管结果变没变，有提交则直接以提交的数据 wpwatermark_options
			update_option('wpwatermark_options', $wpwatermark_options);
			?>
            <div class="notice notice-success settings-error is-dismissible"><p><strong>设置已保存。</strong></p></div>
			<?php }
			if ( isset($_POST['preview_wpnonce']) && wp_verify_nonce($_POST['preview_wpnonce']) ) {
			$demo_img_path = plugin_dir_path( __FILE__ );
			$im_url = $demo_img_path . 'demo.jpg';
			$new_im_url = $demo_img_path . 'preview.jpg';
			if ( $wpwatermark_options['watermark_type'] === 'text_watermark' ) {
			wpWaterMarkCreateWordsWatermark(
			$im_url,
			$new_im_url,
			$wpwatermark_options['text_content'],
			$wpwatermark_options['watermark_spacing'],
			$wpwatermark_options['text_size'],
			$wpwatermark_options['text_color'],
			$wpwatermark_options['watermark_position'],
			$wpwatermark_options['text_font'],
			$wpwatermark_options['text_angle'],
			$wpwatermark_options['watermark_margin']
			);
			} elseif ( $wpwatermark_options['watermark_type'] === 'image_watermark' ) {
			wpWaterMarkCreateImageWatermark(
			$im_url,
			$wpwatermark_options['watermark_mark_image'],
			$new_im_url,
			$wpwatermark_options['watermark_position'],
			$wpwatermark_options['watermark_diaphaneity'],
			$wpwatermark_options['watermark_spacing'],
			$wpwatermark_options['watermark_margin']
			);
			}
			}
	?>
<link rel='stylesheet'  href='<?php echo plugin_dir_url(__FILE__); ?>layui/css/layui.css' />
<link rel='stylesheet'  href='<?php echo plugin_dir_url(__FILE__); ?>layui/css/laobuluo.css'/>
<script src='<?php echo plugin_dir_url(__FILE__); ?>layui/layui.js'></script>
<style>
.layui-table tbody tr:hover {
background-color: white;
}
.preview-img{min-width:280px; width:60%;max-width:80%;}
.preview-img img{max-width: 100%;height: auto;}
</style>
<div class="container-laobuluo-main">
	<div class="laobuluo-wbs-header" style="margin-bottom: 15px;">
		<div class="laobuluo-wbs-logo">
			<a>
				<img src="<?php echo plugin_dir_url(__FILE__); ?>layui/images/logo.png">
			</a><span class="wbs-span">WPWaterMark - WordPress轻水印插件</span><span class="wbs-free">Free V4.2</span>
		</div>
		<div class="laobuluo-wbs-btn">
			<a class="layui-btn layui-btn-primary" href="https://www.lezaiyun.com/?utm_source=wpwatermark-setting&utm_media=link&utm_campaign=header" target="_blank">
				<i class="layui-icon layui-icon-home"></i> 插件主页
			</a>
			<a class="layui-btn layui-btn-primary" href="https://www.lezaiyun.com/wpwatermark.html?utm_source=wpwatermark-setting&utm_media=link&utm_campaign=header" target="_blank">
				<i class="layui-icon layui-icon-release"></i> 插件教程
			</a>
		</div>
	</div>
</div>
<!-- 内容 -->
<div class="container-laobuluo-main">
	<div class="layui-container container-m">
		<div class="layui-row layui-col-space15">
			<!-- 左边 -->
			<div class="layui-col-md9">
				<div class="laobuluo-panel">
					<div class="laobuluo-controw">
						<fieldset class="layui-elem-field layui-field-title site-title">
							<legend>
								<a name="get">
									设置选项
								</a>
							</legend>
						</fieldset>
						<form class="layui-form wpcosform" action="<?php echo wp_nonce_url('./admin.php?page=' . WPWaterMark_INDEXFILE); ?>" name="wpwatermarkform" method="post">
							<table class="layui-table" lay-even >
								<colgroup>
									<col width="120">
									<col>
								</colgroup>
								<tbody>
									<tr>
										<td>水印类型：</td>
										<td>
											<div class="layui-form-item">
												<div class="layui-input-inline">
													<input class="layui-input" lay-filter="water-tab"  type="radio"  name="watermark_type" value="text_watermark" title="本文水印"
													<?php
													if ($wpwatermark_options['watermark_type'] == 'text_watermark') { echo 'checked="checked"';
													}
													?> />
												</div>
												<div class="layui-input-inline">
													<input class="layui-input" lay-filter="water-tab" type="radio" name="watermark_type" value="image_watermark" title="图片水印"
													<?php
													if ($wpwatermark_options['watermark_type'] == 'image_watermark') { echo 'checked="checked"';
													}
													?> />
												</div>
											</div>
											<div class="water-tab text-watermark"style="display:
											<?php
											if ($wpwatermark_options['watermark_type'] == 'text_watermark') {
												echo 'block';
											} else {
												echo 'none';
											}
											?>
											;">

												<div class="layui-form-item">
													<label class="layui-form-label">文本内容：</label>
													<div class="layui-input-inline">
														<input class="ipn-win layui-input" name="text_content" type="text" id="textfield" value="<?php echo esc_attr($wpwatermark_options['text_content']); ?>" size="30" />
													</div>
												</div>
												<div class="layui-form-item">
													<label class="layui-form-label">文本字体：</label>
													<div class="layui-input-inline">
														<select class="ipn-win" id='text_font' name="text_font" required>
															<?php $dir = plugin_dir_path(__FILE__) . 'fonts/';
															$files1 = scandir($dir);
															foreach ($files1 as $k => $v) {
																if ($v != "." && $v != "..") {
																	$is_selected = $wpwatermark_options['text_font'] == $v ? "selected" : "";
																	echo "<option value='$v' $is_selected>$v</option>";
																}
															}
															?>
														</select>
													</div>
												</div>
												<div class="layui-form-item">
													<label class="layui-form-label">文本倾斜：</label>
													<div class="layui-input-inline">
														<input class="ipn-win layui-input" name="text_angle" type="text" value="<?php echo esc_attr($wpwatermark_options['text_angle']); ?>" size="20" />
													</div>
												</div>
												<div class="layui-form-item">
													<label class="layui-form-label">文本大小：</label>
													<div class="layui-input-inline">
														<input class="ipn-win layui-input" name="text_size" type="text" id="textfield2" value="<?php echo esc_attr($wpwatermark_options['text_size']); ?>" size="20" />
													</div>
												</div>
												<div class="layui-form-item">
													<label class="layui-form-label">文本颜色：</label>
													<div class="layui-input-inline">
														<input class="ipn-win layui-input" name="text_color" type="text" value="<?php echo esc_attr($wpwatermark_options['text_color']); ?>" size="15"/>
														<span id="color_code"></span>
													</div>
												</div>
											</div>
											<div class="water-tab image-watermark"style="display:
											<?php
											if ($wpwatermark_options['watermark_type'] == 'image_watermark') {
												echo 'block';
											} else {
												echo 'none';
											}
											?>
											;">
												<div class="layui-form-item mt5">
													<div class="layui-input-block" style="margin-left: 0;width: 315px;">
														<input class=" layui-input" type="text" name="watermark_mark_image" value="<?php echo  esc_attr($wpwatermark_options['watermark_mark_image']) ?>" size="80"/>
													</div>
													<div class="layui-form-mid layui-word-aux">
														说明：自己准备一个水印图片URL地址（比如:https://www.laobuluo.com/watermark.png），最好是透明 .png 图片，尺寸建议250*100px。
													</div>
												</div>
											</div></td>
									</tr>
									<tr>
										<td>加水印条件:</td>
										<td>
											<div class="layui-form-item">
												<label class="layui-form-label"> 宽度：</label>
												<div class="layui-input-inline">
													<input class="layui-input" name="watermark_min_width" type="text" value="<?php echo esc_attr($wpwatermark_options['watermark_min_width']); ?>" size="10" />
												</div>
												<div class="layui-form-mid layui-word-aux">
													（单位为px）
												</div>
											</div>
											<div class="layui-form-item">
												<label class="layui-form-label"> 高度：</label>
												<div class="layui-input-inline">
													<input class="layui-input" name="watermark_min_height" type="text" value="<?php echo esc_attr($wpwatermark_options['watermark_min_height']); ?>" size="10" />
												</div>
												<div class="layui-form-mid layui-word-aux">
													（单位为px）
												</div>
											</div></td>
									</tr>
									<tr>
										<td>其他设置：</td>
										<td>
											<div class="layui-form-item">
												<label class="layui-form-label">水印间距:</label>
												<div class="layui-input-inline">
													<input class="layui-input" name="watermark_margin" type="text"  value="<?php echo esc_attr($wpwatermark_options['watermark_margin']); ?>" size="20" />
												</div>
												<div class="layui-form-mid layui-word-aux">
													（平铺时水印之间距离）
												</div>
											</div>
											<div class="layui-form-item">
												<label class="layui-form-label">水印透明度:</label>
												<div class="layui-input-inline">
													<input class="layui-input" name="watermark_diaphaneity" type="text" id="label4" value="<?php echo esc_attr($wpwatermark_options['watermark_diaphaneity']); ?>" size="20" />
												</div>
												<div class="layui-form-mid layui-word-aux">
													（0-100数值 数值越小越透明）
												</div>
											</div>
											<div class="layui-form-item">
												<label class="layui-form-label">水印边距:</label>
												<div class="layui-input-inline">
													<input class="layui-input" name="watermark_spacing" type="text" id="label3" value="<?php echo esc_attr($wpwatermark_options['watermark_spacing']); ?>" size="20" />
												</div>
												<div class="layui-form-mid layui-word-aux">
													（水印起始位置距离图片四周边距数值，单位为px 建议30px）
												</div>
											</div></td>
									</tr>

									<tr>
										<td>水印位置：</td>
										<td>
											<div class="layui-form-item">
												<div class="layui-input-inline" style="width: 140px">
													<input class=" layui-input" lay-filter="sudoku"  type="radio" title="九宫格位置" name="watermarkPosition" value="jiugongge" <?php
													if ($wpwatermark_options['watermark_position'] > 0 and $wpwatermark_options['watermark_position'] < 10) { echo 'checked="checked"';}?> />
												</div>
												<div class="layui-form-mid layui-word-aux">
													（1-9 固定位置）
												</div>
												<div class="layui-input-block mt5 sudoku-box" style="margin-left: 0; width:315px;display: 
												<?php
												if ($wpwatermark_options['watermark_position'] > 0 and $wpwatermark_options['watermark_position'] < 10) {
													echo 'block';
												} else {
													echo 'none';
												}
												?>">
													<input class=" layui-input"  name="jiugongge_value" type="text" id="label2" value=<?php
													if ($wpwatermark_options['watermark_position'] > 0 and $wpwatermark_options['watermark_position'] < 10) { echo '"' . esc_attr($wpwatermark_options['watermark_position']) . '"';
													    } else { echo '"" disabled';
													}?> size="10" />
												</div>

											</div>
											<div class="layui-form-item">
												<div class="layui-input-inline" style="width: 140px">
													<input type="radio" lay-filter="sudoku" title="随机九宫格"  name="watermarkPosition" value="suiji" <?php if ($wpwatermark_options['watermark_position'] == 0) { echo 'checked="checked"';}?>/>
												</div>
												<div class="layui-form-mid layui-word-aux">
													（每次水印位置随机）
												</div>
											</div>
											<div class="layui-form-item">
												<div class="layui-input-inline" style="width: 140px">
													<input type="radio"  lay-filter="sudoku"  title="满铺水印效果" name="watermarkPosition" value="manpu" <?php if ($wpwatermark_options['watermark_position'] == 10) { echo 'checked="checked"';}?>/>
												</div>
												<div class="layui-form-mid layui-word-aux">
													（满铺水印效果，强力防御）
												</div>
											</div>
											<input type="hidden" name="watermark_position" value="<?php echo esc_attr($wpwatermark_options['watermark_position']); ?>" />
										</td>
									</tr>
									<tr>
										<td></td>
										<td>
											<input type="submit" name="submit" value="保存轻水印插件设置" class="layui-btn" style="width: 176px;"/>
										</td>
									</tr>
									<tr>
										<td>演示效果：</td>
										<td>
											<input type="button" id="preview" value="点击刷新预览水印效果" class="layui-btn  layui-btn-normal" />
											<p class="mt10 preview-img" id="preview_block"></p></td>
									</tr>
								</tbody>
							</table>
						</form>
					</div>
				</div>
			</div>
			<!-- 左边 -->
			<!-- 右边 -->
			<div class="layui-col-md3">
				<div id="nav">
					 <div class="laobuluo-panel">
                        <div class="laobuluo-panel-title">关注公众号</div>
                        <div class="laobuluo-code">
                            <img src="<?php echo plugin_dir_url(__FILE__); ?>layui/images/qrcode.png">
                            <p>微信扫码关注 <span class="layui-badge layui-bg-blue">老蒋朋友圈</span> 公众号</p>
                            <p><span class="layui-badge">优先</span> 获取插件更新 和 更多 <span class="layui-badge layui-bg-green">免费插件</span> </p>
                        </div>
                    </div>

                   <div class="laobuluo-panel">
                            <div class="laobuluo-panel-title">站长必备资源</div>
                            <div class="laobuluo-shangjia">
                                <a href="https://www.lezaiyun.com/webmaster-tools.html" target="_blank" title="站长必备资源">
                                    <img src="<?php echo plugin_dir_url( __FILE__ );?>layui/images/cloud.jpg"></a>
                                    <p>站长必备的商家、工具资源整理！</p>
                            </div>
                        </div>
				</div>
			</div>
			<!-- 右边 -->
		</div>
	</div>
</div>
<!-- 内容 -->
<!-- footer -->
<div class="container-laobuluo-main">
	<div class="layui-container container-m">
		<div class="layui-row layui-col-space15">
			<div class="layui-col-md12">
				<div class="laobuluo-footer-code">
					<span class="codeshow"></span>
				</div>
				<div class="laobuluo-links">
					 <a href="https://www.laobuluo.com/?utm_source=wpreplace-setting&utm_media=link&utm_campaign=footer"  target="_blank">老部落</a>
					<a href="https://www.laobuluo.com/?utm_source=wpwatermark-setting&utm_media=link&utm_campaign=footer"  target="_blank">
						乐在云
					</a>
					<a href="https://www.lezaiyun.com/donate/?utm_source=wpwatermark-setting&utm_media=link&utm_campaign=footer"  target="_blank">
						赞助插件
					</a>
					<a href="https://www.lezaiyun.com/wpwatermark.html?utm_source=wpwatermark-setting&utm_media=link&utm_campaign=footer"  target="_blank">
						使用说明
					</a>
					<a href="https://www.lezaiyun.com/about/?utm_source=wpwatermark-setting&utm_media=link&utm_campaign=footer"  target="_blank">
						关于我们
					</a>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- footer -->
<script>
			layui.use(['form', 'element','jquery'], function(){
			var $ =layui.jquery;
			var form = layui.form;
			var element = layui.element
			
			function menuFixed(id) {
			var obj = document.getElementById(id);
			var _getHeight = obj.offsetTop;
			var _Width= obj.offsetWidth
			window.onscroll = function () {
			changePos(id, _getHeight,_Width);
			}
			}
			
			function changePos(id, height,width) {
			var obj = document.getElementById(id);
			obj.style.width = width+'px';
			var scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
			var _top = scrollTop-height;
			if (_top < 150) {
			var o = _top;
			obj.style.position = 'relative';
			o = o > 0 ? o : 0;
			obj.style.top = o +'px';
			
			} else {
			obj.style.position = 'fixed';
			obj.style.top = 50+'px';
			
			}
			}
			menuFixed('nav');
			
			form.on('radio(water-tab)', function(data){
			
			$(".water-tab").hide()
			if (this.value == 'text_watermark') {
			$('.text-watermark').show()
			} else if (this.value == 'image_watermark') {
			$('.image-watermark').show()
			}
			})
			
			form.on('radio(sudoku)',function(){
			
			  $('input[name="jiugongge_value"]').attr('disabled', true);
			  $('.sudoku-box').hide();
			
			  if (this.value=='jiugongge') {
				   $('.sudoku-box').show()
				   $('input[name="watermark_position"]').val($('input[name="jiugongge_value"]').val());
				   $('input[name="jiugongge_value"]').attr("disabled", false);
				
			    } else if(this.value=='suiji'){
				   $('input[name="watermark_position"]').val('0');
				} else if(this.value == 'manpu'){
				   $('input[name="watermark_position"]').val('10');
				}
			})
			
			$('input[name="jiugongge_value"]').change(function(){
			    $('input[name="watermark_position"]').val($('input[name="jiugongge_value"]').val());
			});
			
			function readyFn() {
			function randomNum(minNum,maxNum){
			switch(arguments.length){
			case 1:
			return parseInt(Math.random()*minNum+1,10);
			break;
			case 2:
			return parseInt(Math.random()*(maxNum-minNum+1)+minNum,10);
			break;
			default:
			return 0;
			break;
			}
			}
			
			$('input[type="color"]').colorpicker({hoverChange:true});
			$('input[name="text_color"]').colorpicker({
			'onSelect':function(color){
			$('#color_code').text('（颜色修改为：'+color+'）');
			}
			});
			$('#preview').click(function () {
			
			let watermark_type;
			if ( $('input[value="text_watermark"]').is(':checked') ) {
			watermark_type = "text_watermark";
			}
			if ( $('input[value="image_watermark"]').is(':checked') ) {
			watermark_type = "image_watermark";
			}
			let text_content = $('input[name="text_content"]').val();
			let text_font = $('#text_font option:selected').val();
			let text_angle = $('input[name="text_angle"]').val();
			let text_size = $('input[name="text_size"]').val();
			let text_color = $('input[name="text_color"]').val();
			let watermark_mark_image = $('input[name="watermark_mark_image"]').val();
			let watermark_position = $('input[name="watermark_position"]').val();
			let watermark_margin = $('input[name="watermark_margin"]').val();
			let watermark_diaphaneity = $('input[name="watermark_diaphaneity"]').val();
			let watermark_spacing = $('input[name="watermark_spacing"]').val();
			
			$.post(
			"<?php echo './admin.php?page=' . WPWaterMark_INDEXFILE; ?>",
			{
			'preview_wpnonce': "<?php echo wp_create_nonce(); ?>",
			'watermark_type': watermark_type,
			'text_content': text_content,
			'text_font': text_font,
			'text_angle': text_angle,
			'text_size': text_size,
			'text_color': text_color,
			'watermark_mark_image': watermark_mark_image,
			'watermark_position': watermark_position,
			'watermark_margin': watermark_margin,
			'watermark_diaphaneity': watermark_diaphaneity,
			'watermark_spacing': watermark_spacing,
			},
			function( res ) {
// if ( res['status'] ==1 ) {
             let x = res;
             let img_src = "<?php echo plugins_url('preview.jpg', __FILE__) . '?' ?>" + randomNum(0, 99999);
             let img_code = "<img src='" + img_src + "' />";
              $('#preview_block').html(img_code);
              },
);
});
			}
			$(document).ready(readyFn);

})
</script>

<?php
}
?>