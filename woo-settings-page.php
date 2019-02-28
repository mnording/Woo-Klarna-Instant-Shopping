<?php
require 'klarna-instant-shopping-buttons-generator.php';
class WooKlarnaInstantShoppingSettingsPage
{
    private $euliveid;
    private $eulivepass;
    private $eutestid;
    private $eutestpass;
    private $buttonGenerator;
    function __construct()
    {
        add_action('admin_init', array($this, 'plugin_admin_init'));
        if (get_option('woocommerce_klarna_payments_settings')) {
            $gateway_settings = get_option('woocommerce_klarna_payments_settings');
            $gateway_title    = 'Klarna Payments';
        } elseif ($gateway_settings = get_option('woocommerce_kco_settings')) {
            $gateway_settings = get_option('woocommerce_kco_settings');
            $gateway_title    = 'Klarna Checkout';
        }
        $this->eutestid = $gateway_settings["test_merchant_id_eu"];
        $this->eutestpass = $gateway_settings["test_shared_secret_eu"];
        $this->euliveid = $gateway_settings["merchant_id_eu"];
        $this->eulivepass = $gateway_settings["shared_secret_eu"];
        $this->buttonGenerator = new ButtonGenerator($this->getmid(), $this->getpass(), $this->getBaseUrl());
    }
    function getmid()
    {
        if ($this->getTestmode()) {
            return $this->eutestid;
        }
        return $this->euliveid;
    }
    function getBaseUrl()
    {
        if ($this->getTestmode()) {
            return "https://api.playground.klarna.com";
        }
        return "https://api.klarna.com";
    }
    function getpass()
    {
        if ($this->getTestmode()) {
            return $this->eutestpass;
        }
        return $this->eulivepass;
    }
    function getTestmode()
    {
        $testmode = get_option("woo-klarna-instant-shopping");
        return isset($testmode["testmode"]);
    }
    function shouldLogDebug()
    {
        $options =  get_option("woo-klarna-instant-shopping");
        return isset($options["logdebug"]);
    }
    function RenderKlarnaSettingsPage()
    {
        ?>
<div>
    <h2>Klarna Instant Shopping</h2>
    <form action="options.php" method="post">
        <?php settings_fields('woo-klarna-instant-shopping'); ?>
        <?php do_settings_sections('woo-klarna-instant-shopping'); ?>

        <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
    </form>
</div>
<? if (!get_option("woo-klarna-instant-shopping-buttonid")) {
    ?>
<form method="post">
    <?php if (isset($_POST["newbutton"])) {
        $buttonID = $this->CreateNewButton();

        update_option("woo-klarna-instant-shopping-buttonid", $buttonID);
    } ?>
    <button name="newbutton">Create new button</button>
    <?php echo get_option("woo-klarna-instant-shopping-buttonid") ?>
</form>
<?
}
}
function CreateNewButton()
{

    return $this->buttonGenerator->generateButtonKey();
}
function plugin_admin_init()
{

    register_setting('woo-klarna-instant-shopping', 'woo-klarna-instant-shopping', 'plugin_options_validate');
    add_settings_section('plugin_main', 'Main Settings', array($this, 'plugin_section_text'), 'woo-klarna-instant-shopping');
    add_settings_field('testmode', 'Testmode enabled', function () {
        $this->plugin_setting_checkbox("testmode");
    }, 'woo-klarna-instant-shopping', 'plugin_main');
    add_settings_field('logdebug', 'Log debug', function () {
        $this->plugin_setting_checkbox("logdebug");
    }, 'woo-klarna-instant-shopping', 'plugin_main');
    add_settings_section('Buttons', 'Buttons', array($this, 'listButtons'), 'woo-klarna-instant-shopping');
}
function listButtons()
{
    echo '<p>here are all the buttons we have on file</p>';
    echo '<p>' . get_option("woo-klarna-instant-shopping-buttonid") . '</p>';
}
function plugin_section_text()
{
    echo '<p>Main description of this section here.</p> <p>All the options you might need for instant shopping</p>
        <p><i>All credentials will be fetched from your main Klarna plugin</i></p>';
}
function plugin_setting_checkbox($key)
{
    $options = get_option('woo-klarna-instant-shopping');

    if (!isset($options[$key])) {
        $options[$key] = "";
    }
    ?>
<input name='woo-klarna-instant-shopping[<?php echo $key ?>]' type='checkbox' value='1' <?php checked("1", $options[$key], true) ?> />
<?php

}
function plugin_setting_selectpage($key)
{
    $options = get_option('woo-klarna-instant-shopping');
    if (!isset($options[$key])) {
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

    echo '<select name="woo-klarna-instant-shopping[' . $key . ']">';
    foreach ($pages as $page) {
        $selected = ($options[$key] == $page->ID) ? "selected" : "";
        echo '<option ' . $selected . ' value="' . $page->ID . '">' . $page->post_title . '</option>';
    }
    echo '</select> ';
}
}
?> 