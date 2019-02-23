<?php
class WooKlarnaInstantShoppingSettingsPage {
    function __construct()
    {
        add_action('admin_init', array($this,'plugin_admin_init'));
    }
    function RenderKlarnaSettingsPage() {
        ?>
        <div>
        <h2>My custom plugin</h2>
        Options relating to the Custom Plugin.
        <form action="options.php" method="post">
        <?php settings_fields('woo-klarna-instant-shopping'); ?>
        <?php do_settings_sections('woo-klarna-instant-shopping'); ?>
         
        <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
        </form></div>
         
        <?php
        }
        function plugin_admin_init(){
            register_setting( 'woo-klarna-instant-shopping', 'woo-klarna-instant-shopping', 'plugin_options_validate' );
            add_settings_section('plugin_main', 'Main Settings', array($this,'plugin_section_text'), 'woo-klarna-instant-shopping');
            add_settings_field('api_username', 'Api Username', function() { $this->plugin_setting_string("api_username","text"); }, 'woo-klarna-instant-shopping', 'plugin_main');
            add_settings_field('api_pass', 'Api Password', function() { $this->plugin_setting_string("api_pass","text"); }, 'woo-klarna-instant-shopping', 'plugin_main');
            add_settings_field('thankyoupage', 'Thank you page', function() { $this->plugin_setting_selectpage("thankyoupage"); }, 'woo-klarna-instant-shopping', 'plugin_main');
}
function plugin_section_text() {
    echo '<p>Main description of this section here.</p>';
    }
    function plugin_setting_string($key,$type) {
        $options = get_option('woo-klarna-instant-shopping');
        if(!isset($options[$key])){
            $options[$key] = "";
        }
        echo "<input id='".$key."' name='woo-klarna-instant-shopping[".$key."]' size='40' type='".$type."' value='{$options[$key]}' />";
        }
        function plugin_setting_selectpage($key){
            $options = get_option('woo-klarna-instant-shopping');
            if(!isset($options[$key])){
                $options[$key] = -1;
            }
            $args = array(
                'sort_order' => 'asc',
                'sort_column' => 'post_title',
                'parent' => -1,
                'post_type' => 'page',
                'post_status' => 'publish'
            ); 
            $pages = get_pages($args); 
            echo $options[$key];
            echo '<select name="woo-klarna-instant-shopping['.$key.']">';
            foreach($pages as $page)
            {
                $selected = ($options[$key] == $page->ID) ? "selected" : "";
                echo '<option '.$selected.' value="'.$page->ID.'">'.$page->post_title.'</option>';
            }
            echo '</select>';
        }
}
?>