<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 专为 Typecho 1.3.0 开发的 TinyMCE 8 编辑器
 * 支持：本地/CDN切换、附件接管、暗黑模式、工具栏预设
 *
 * @package TinyMCE8
 * @author JamesLi
 * @version 1.0.0
 * @link https://jamesjie.com
 */
class TinyMCE8_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('admin/write-post.php')->richEditor = array('TinyMCE8_Plugin', 'render');
        Typecho_Plugin::factory('admin/write-page.php')->richEditor = array('TinyMCE8_Plugin', 'render');
        return _t('TinyMCE 8 插件已激活！请进入“设置”自定义功能。');
    }

    public static function deactivate() { return _t('插件已禁用。'); }

    /**
     * 插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 1. 编辑器高度
        $editorHeight = new Typecho_Widget_Helper_Form_Element_Text('editorHeight', NULL, '600', _t('编辑器高度'), _t('请输入纯数字，默认为 600。'));
        $form->addInput($editorHeight);

        // 2. 资源加载方式
        $loadType = new Typecho_Widget_Helper_Form_Element_Radio('loadType', 
            array('local' => _t('本地资源 (tinymce目录)'), 'cdn' => _t('云端 CDN (Unpkg)')), 
            'local', _t('资源加载方式'), _t('若服务器带宽低建议选CDN；若网络环境不佳选本地。'));
        $form->addInput($loadType);

        // 3. 菜单栏开关
        $menubarToggle = new Typecho_Widget_Helper_Form_Element_Radio('menubarToggle',
            array('true' => _t('显示'), 'false' => _t('隐藏')),
            'true', _t('显示菜单栏'), _t('是否显示编辑器顶部的“文件、编辑、视图”等菜单。'));
        $form->addInput($menubarToggle);

        // 4. 外观皮肤
        $editorTheme = new Typecho_Widget_Helper_Form_Element_Select('editorTheme',
            array('oxide' => _t('明亮模式'), 'oxide-dark' => _t('暗黑模式')),
            'oxide', _t('编辑器皮肤'), _t('切换编辑器的视觉风格。'));
        $form->addInput($editorTheme);

        // 5. 工具栏预设
        $toolbarStyle = new Typecho_Widget_Helper_Form_Element_Select('toolbarStyle',
            array(
                'basic' => _t('极简模式 (仅基础格式)'),
                'standard' => _t('标准模式 (常用功能)'),
                'full' => _t('全功能模式 (含表格、媒体等)')
            ), 'standard', _t('工具栏预设'), _t('根据您的写作习惯选择工具栏复杂度。'));
        $form->addInput($toolbarStyle);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 渲染并注入编辑器
     */
    public static function render($post = null)
    {
        // 读取保存的配置
        $settings = Helper::options()->plugin('TinyMCE8');
        
        // 处理加载路径
        $pluginUrl = Helper::options()->pluginUrl . '/TinyMCE8';
        $coreScript = ($settings->loadType == 'cdn') 
            ? 'https://unpkg.com/tinymce@8.4.0/tinymce.min.js' 
            : $pluginUrl . '/tinymce/tinymce.min.js';

        // 处理工具栏预设
        $toolbarConfig = 'undo redo | blocks | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | link image | removeformat';
        if ($settings->toolbarStyle == 'basic') {
            $toolbarConfig = 'undo redo | bold italic | link image | removeformat';
        } elseif ($settings->toolbarStyle == 'full') {
            $toolbarConfig = 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | table media charmap | fullscreen code help';
        }

        // 处理皮肤
        $skin = $settings->editorTheme;
        $contentCss = ($skin == 'oxide-dark') ? 'dark' : 'default';

?>
<script src="<?php echo $coreScript; ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. 隐藏默认元素
    const hideElements = ['wmd-button-bar', 'wmd-preview'];
    hideElements.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });

    // 2. 初始化 TinyMCE
    tinymce.init({
        selector: '#text',
        license_key: 'gpl',
        height: <?php echo $settings->editorHeight; ?>,
        menubar: <?php echo $settings->menubarToggle; ?>,
        promotion: false,
        branding: false,
        skin: '<?php echo $skin; ?>',
        content_css: '<?php echo $contentCss; ?>',
        language: 'zh_CN',
        language_url: '<?php echo $pluginUrl; ?>/langs/zh_CN.js',
        
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor',
            'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        
        toolbar: '<?php echo $toolbarConfig; ?>',
        
        content_style: 'body { font-family: -apple-system, system-ui, sans-serif; font-size: 16px; line-height: 1.6; padding: 20px; }',
        
        paste_data_images: false, // 强制走附件系统
        
        setup: function (editor) {
            editor.on('change input keyup', function () {
                editor.save(); 
            });
        }
    });

    // 3. 接管附件点击事件
    window.Typecho = window.Typecho || {};
    window.Typecho.insertFileToEditor = function (file, url, isImage) {
        const editor = tinymce.get('text'); 
        if (editor) {
            const html = isImage 
                ? '<img src="' + url + '" alt="' + file + '" style="max-width: 100%; height: auto;" />' 
                : '<a href="' + url + '" target="_blank">' + file + '</a>';
            editor.insertContent(html);
        }
    };
});
</script>
<?php
    }
}