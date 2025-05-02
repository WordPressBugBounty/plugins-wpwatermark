jQuery(document).ready(function($) {
    // 初始化颜色选择器
    $('.wpwatermark-color-picker').wpColorPicker();
    
    // 水印类型切换
    $('input[name="watermark_type"]').change(function() {
        if ($(this).val() === 'text_watermark') {
            $('.text-watermark-options').show();
            $('.image-watermark-options').hide();
        } else {
            $('.text-watermark-options').hide();
            $('.image-watermark-options').show();
        }
    });
    
    // 媒体上传器
    $('.wpwatermark-upload-button').click(function(e) {
        e.preventDefault();
        
        var frame = wp.media({
            title: '选择水印图片',
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#watermark_mark_image').val(attachment.url);
            $('.wpwatermark-media-preview').html('<img src="' + attachment.url + '" alt="水印预览">');
        });
        
        frame.open();
    });
    
    // 水印位置选择器
    $('.wpwatermark-position-selector button').click(function() {
        var position = $(this).data('position');
        $('#watermark_position').val(position);
        $('.wpwatermark-position-selector button').removeClass('active');
        $(this).addClass('active');
    });
    
    // 表单验证
    $('.wpwatermark-form').on('submit', function(e) {
        var type = $('input[name="watermark_type"]:checked').val();
        
        if (type === 'text_watermark') {
            var content = $('input[name="text_content"]').val().trim();
            if (!content) {
                e.preventDefault();
                alert('请输入水印文字内容');
                return false;
            }
        } else {
            var imageUrl = $('#watermark_mark_image').val().trim();
            if (!imageUrl) {
                e.preventDefault();
                alert('请选择水印图片');
                return false;
            }
        }
        
        return true;
    });
}); 