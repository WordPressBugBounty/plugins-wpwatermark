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
    
    /**
     * 以隐藏域 watermark_position 为权威状态同步单选与九宫格（避免 :checked 为空时把 random 误判成固定）。
     */
    function wpwatermarkSyncPositionMode() {
        var $hidden = $('#watermark_position');
        var $grid = $('.wpwatermark-position-selector');
        var cur = $hidden.val();
        var isRandom = (cur === 'random');

        if (isRandom) {
            $('input[name="wpwatermark_position_mode"][value="random"]').prop('checked', true);
            $grid.css('opacity', '0.5');
            $grid.find('button').prop('disabled', true).removeClass('active');
        } else {
            $('input[name="wpwatermark_position_mode"][value="fixed"]').prop('checked', true);
            $grid.css('opacity', '1');
            $grid.find('button').prop('disabled', false);
            if (!cur) {
                cur = 'bottom-right';
                $hidden.val(cur);
            }
            $grid.find('button').removeClass('active');
            $grid.find('button[data-position="' + cur + '"]').addClass('active');
        }
    }

    $('input[name="wpwatermark_position_mode"]').on('change', function() {
        if ($(this).val() === 'random') {
            $('#watermark_position').val('random');
        } else {
            var h = $('#watermark_position').val();
            if (h === 'random' || !h) {
                var fallback = $('.wpwatermark-position-selector button.active').data('position') || 'bottom-right';
                $('#watermark_position').val(fallback);
            }
        }
        wpwatermarkSyncPositionMode();
    });

    wpwatermarkSyncPositionMode();

    // 水印位置选择器
    $('.wpwatermark-position-selector button').click(function() {
        $('input[name="wpwatermark_position_mode"][value="fixed"]').prop('checked', true);
        var position = $(this).data('position');
        $('#watermark_position').val(position);
        $('.wpwatermark-position-selector button').removeClass('active');
        $(this).addClass('active');
        wpwatermarkSyncPositionMode();
    });
    
    // 表单验证
    $('.wpwatermark-form').on('submit', function(e) {
        var mode = $('input[name="wpwatermark_position_mode"]:checked').val();
        if (mode === 'random') {
            $('#watermark_position').val('random');
        } else if (mode === 'fixed') {
            var h = $('#watermark_position').val();
            if (h === 'random' || !h) {
                var active = $('.wpwatermark-position-selector button.active').data('position') || 'bottom-right';
                $('#watermark_position').val(active);
            }
        }
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
