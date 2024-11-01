<?php
/*
* Plugin Name: ThueSMS - Gửi tin nhắn sms Online
* Version: 1.0.1
* Description: Gửi tin nhắn vào số điện thoại của khách hàng khi sử dụng Contact Form 7, NinjaForms hoặc Woocommerce. Bắt buộc phải cài 1 trong 3 plugin để plugin có thể hoạt động.
* Author: ThueSMS.com
* Author URI: https://thuesms.com/
* Plugin URI: https://thuesms.com/plugins-thue-sms/
* Lấy API: https://thuesms.com/home-api-v2/
* Text Domain: ThueSMS
* Domain Path: /languages
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
if (
    in_array( 'contact-form-7/wp-contact-form-7.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )
    ||
    in_array( 'ninja-forms/ninja-forms.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )
    ||
    in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )
){
    if (!class_exists('thuesms_Class')) {
        class thuesms_Class
        {
            protected static $instance;
            public $_version = '1.0.1';

            public $_optionName = 'thuesms_options';
            public $_optionGroup = 'thuesms-options-group';
            public $_defaultOptions = array(
                'kichhoat'      =>  '',
                'check_version' =>  '',
                'apikey'        =>  '',
                'secretkey'     =>  '',
                'mess_content'  =>  '',
                'cf7_id'        =>  '',
                'smstype'       =>  8,
                'is_unicode'    =>  1,
                'brandname'     =>  '',
                'mess_content_list' => array(),

                'enable_woo'    =>  '',
                'admin_phone' =>  '',

                'account_creat_mess'    =>  '',
                'account_creat'    =>  '',

                'order_creat'    =>  '',
                'order_creat_mess' =>  '',

                'woo_status_complete' =>  '',
                'woo_status_complete_mess' =>  '',

                'woo_status_processing' =>  '',
                'woo_status_processing_mess' =>  '',

                'woo_status_cancelled' =>  '',
                'woo_status_cancelled_mess' =>  '',

                'order_creat_admin' =>  '',
                'order_creat_admin_mess'    =>  '',

                'sandbox'    =>  '0',
                
            );

            public static function init()
            {
                is_null(self::$instance) AND self::$instance = new self;
                return self::$instance;
            }

            public function __construct()
            {
                $this->define_constants();
                global $thuesms_settings;
                $thuesms_settings = $this->get_options();

                add_action('plugins_loaded', array($this, 'dvls_load_textdomain'));

                add_filter('plugin_action_links_' . AATHUAT_thuesms_BASENAME, array($this, 'add_action_links'), 10, 2);

                add_action('admin_menu', array($this, 'admin_menu'));
                add_action('admin_init', array($this, 'dvls_register_mysettings'));

                add_action( 'wpcf7_mail_sent', array($this, 'process_contact_form_data') );
                if(!$thuesms_settings['check_version']) {
                    add_action('ninja_forms_after_submission', array($this, 'process_ninjaform_data'));
                }else {
                    add_action('ninja_forms_post_process', array($this, 'process_ninjaform_data_oldversion'));
                }

                add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

                if($thuesms_settings['kichhoat'] && $thuesms_settings['enable_woo']) {

                    add_action('woocommerce_checkout_process', array($this, 'AATHUAT_validate_phone_field_process') );

                    add_action('woocommerce_created_customer', array($this, 'sms_woocommerce_created_customer'), 10, 2);

                    if (
                        ($thuesms_settings['order_creat'] && $thuesms_settings['order_creat_mess']) ||
                        ($thuesms_settings['order_creat_admin'] && $thuesms_settings['order_creat_admin_mess'])
                    ) {
                        add_action('woocommerce_new_order', array($this, 'sms_woocommerce_new_order'), 10);
                    }

                    if (
                        (
                            ($thuesms_settings['woo_status_complete'] && $thuesms_settings['woo_status_complete_mess']) ||
                            ($thuesms_settings['woo_status_processing'] && $thuesms_settings['woo_status_processing_mess']) ||
                            ($thuesms_settings['woo_status_cancelled'] && $thuesms_settings['woo_status_cancelled_mess'])
                        )
                    ) {
                        add_action('woocommerce_order_status_changed', array($this, 'sms_woocommerce_order_status_changed'), 10, 3);
                    }
                }

            }

            public function define_constants()
            {
                if (!defined('AATHUAT_thuesms_VERSION_NUM'))
                    define('AATHUAT_thuesms_VERSION_NUM', $this->_version);
                if (!defined('AATHUAT_thuesms_URL'))
                    define('AATHUAT_thuesms_URL', plugin_dir_url(__FILE__));
                if (!defined('AATHUAT_thuesms_BASENAME'))
                    define('AATHUAT_thuesms_BASENAME', plugin_basename(__FILE__));
                if (!defined('AATHUAT_thuesms_PLUGIN_DIR'))
                    define('AATHUAT_thuesms_PLUGIN_DIR', plugin_dir_path(__FILE__));
            }

            public function add_action_links($links, $file)
            {
                if (strpos($file, 'Thuesms.com-.php') !== false) {
                    $settings_link = '<a href="' . admin_url('options-general.php?page=setting-thuesms') . '" title="' . __('Cài đặt', 'aathuat-code') . '">' . __('Cài đặt', 'aathuat-code') . '</a>';
                    array_unshift($links, $settings_link);
                }
                return $links;
            }
            function dvls_load_textdomain()
            {
                load_textdomain('aathuat-code', dirname(__FILE__) . '/languages/aathuat-code-' . get_locale() . '.mo');
            }

            function get_options()
            {
                return wp_parse_args(get_option($this->_optionName), $this->_defaultOptions);
            }

            function admin_menu()
            {
                add_options_page(
                    __('Cài đặt ThuêSMS', 'aathuat-code'),
                    __('Cài đặt ThuêSMS', 'aathuat-code'),
                    'manage_options',
                    'setting-thuesms',
                    array(
                        $this,
                        'AATHUAT_settings_page'
                    )
                );
            }

            function dvls_register_mysettings()
            {
                register_setting($this->_optionGroup, $this->_optionName);
            }

            function AATHUAT_settings_page()
            {
                global $thuesms_settings;
                $ninjsSelect = array();
                if(function_exists('Ninja_Forms')) {
                    if( isset(Ninja_Forms()->menus) ){
                        $ninjaForms = Ninja_Forms()->form()->get_forms();
                        if ($ninjaForms && !empty($ninjaForms)) {
                            foreach ($ninjaForms as $form) {
                                if (is_object($form)) {
                                    $id = $form->get_id();
                                    $name = $form->get_setting('title');
                                    $ninjsSelect['ninja_'.$id] = $name;
                                }
                            }
                        }
                    }else {
                        $ninjaForms = Ninja_Forms()->forms()->get_all();
                        if ($ninjaForms && !empty($ninjaForms)) {
                            foreach ($ninjaForms as $formid) {
                                $id = $formid;
                                $data = Ninja_Forms()->form( $id )->get_all_settings();
                                $name = $data['form_title'];
                                $ninjsSelect['ninja_'.$id] = $name;
                            }
                        }
                    }
                }
                $args = array('post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1);
                $cf7Select = array();
                if( $data = get_posts($args)){
                    foreach($data as $key){
                        $cf7Select['cf7_'.$key->ID] = $key->post_title;
                    }
                }
                ?>
                <div class="wrap">
                    <h1>Cài đặt SMS</h1>
                    <p>Số dư hiện tại ở ThuêSMS là: <strong style="color: #ff0202;"><?php echo $this->get_balance_esms();?> VNĐ</strong></p>
                    <form method="post" action="options.php" novalidate="novalidate">
                        <?php settings_fields($this->_optionGroup); ?>
                        <table class="form-table">
                            <tbody>
                            <tr>
                                <th scope="row"><label for="kichhoat"><?php _e('Kích hoạt', 'aathuat-code') ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" name="<?php echo $this->_optionName ?>[kichhoat]" id="kichhoat" value="1" <?php checked('1',intval($thuesms_settings['kichhoat']), true) ; ?>/>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="admin_phone"><?php _e('Số điện thoại của Admin', 'aathuat-code') ?></label>
                                </th>
                                <td>
                                    <input type="text" name="<?php echo $this->_optionName ?>[admin_phone]" id="admin_phone" value="<?php echo $thuesms_settings['admin_phone'];?>"/><br>
                                    <small>KHÔNG bắt buộc. Có thể thêm nhiều số ADMIN. Ví dụ: 0912345678, 0812345678...</small>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <?php
                        if (
                            in_array( 'contact-form-7/wp-contact-form-7.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )
                            ||
                            in_array( 'ninja-forms/ninja-forms.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )
                        ){
                        ?>
                        <h2>Cài đặt tin nhắn cho Contact Form 7 và NinjaForms</h2>
                        <table class="form-table">
                            <tbody>
                            <tr>
                                <th scope="row"><label for="check_version"><?php _e('NinjaForm version cũ', 'aathuat-code') ?></label></th>
                                <td>
                                    <input type="checkbox" name="<?php echo $this->_optionName ?>[check_version]" id="check_version" value="1" <?php checked('1',intval($thuesms_settings['check_version']), true) ; ?>/> Check vào đây nếu bạn đang chạy NinjaForm version cũ
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mess_content"><?php _e('Nội dung tin nhắn', 'aathuat-code') ?><br><small><?php _e('Nhập nội dung tin nhắn tương ứng với mỗi form','aathuat-code');?></small></label>
                                </th>
                                <td class="dbh-metabox-wrap">
                                    <table class="widefat AATHUAT_bh_tablesetting">
                                        <thead>
                                        <tr>
                                            <th>Nội dung tin nhắn</th>
                                            <th>Chọn Form tương ứng</th>
                                            <th>ID Field SĐT<br><small>Dành cho NinjaForm phiên bản cũ</small></th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody class="thuesms_tbody">
                                        <?php
                                        $thuesms_sanpham = $thuesms_settings['mess_content_list'];
                                        if($thuesms_sanpham):
                                            $stt = 0;
                                            foreach ($thuesms_sanpham as $mess):
                                                $content = isset($mess['content']) ? esc_textarea($mess['content']) : '';
                                                $formID = isset($mess['formID']) ? $mess['formID'] : '';
                                                $sdtField = isset($mess['field_sdt_id']) ? $mess['field_sdt_id'] : '';
                                                $send_admin = isset($mess['send_admin']) ? $mess['send_admin'] : '';
                                                $content_send_admin = isset($mess['content_send_admin']) ? esc_textarea($mess['content_send_admin']) : '';
                                            ?>
                                            <tr>
                                                <td>
                                                    <textarea name="<?php echo $this->_optionName ?>[mess_content_list][id_<?php echo $stt;?>][content]"><?php echo $content;?></textarea>
                                                    <p>
                                                        <label><input type="checkbox" name="<?php echo $this->_optionName ?>[mess_content_list][id_<?php echo $stt;?>][send_admin]" value="1" <?php checked(1,$send_admin)?>> Gửi tin nhắn cho admin</label>
                                                    </p>
                                                    <p>
                                                        <textarea name="<?php echo $this->_optionName ?>[mess_content_list][id_<?php echo $stt;?>][content_send_admin]" placeholder="Nội dung tin nhắn cho admin"><?php echo $content_send_admin;?></textarea>
                                                    </p>
                                                </td>
                                                <td>
                                                    <select name="<?php echo $this->_optionName ?>[mess_content_list][id_<?php echo $stt;?>][formID]">
                                                        <option value=""><?php _e('Chọn Form','aathuat-code');?></option>
                                                        <?php
                                                        if($ninjsSelect){
                                                            echo '<optgroup label="NinjaForms">';
                                                            foreach ($ninjsSelect as $k=>$v){
                                                                echo '<option value="'.$k.'" '.selected($k,$formID,false).'>'.$v.'</option>';
                                                            }
                                                            echo '</optgroup>';
                                                        }
                                                        if($cf7Select){
                                                            echo '<optgroup label="Contact Form 7">';
                                                            foreach ($cf7Select as $k=>$v){
                                                                echo '<option value="'.$k.'" '.selected($k,$formID,false).'>'.$v.'</option>';
                                                            }
                                                            echo '</optgroup>';
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                                <td><input type="number" name="<?php echo $this->_optionName ?>[mess_content_list][id_<?php echo $stt;?>][field_sdt_id]" value="<?php echo $sdtField;?>"/></td>
                                                <td><input type="button" class="button AATHUAT_button AATHUAT_delete_esms" value="Xóa"></td>
                                            </tr>
                                            <?php $stt++; endforeach;?>
                                        <?php endif;?>
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <td colspan="3"><input type="button" class="button AATHUAT_button AATHUAT_add_esms" value="Thêm tin nhắn"></td>
                                        </tr>
                                        </tfoot>
                                    </table>
                                    <small>
<span style="color: red;">Contact Form 7 và NinjaForms:</span> Field số điện thoại <span style="color: red;">BẮT BUỘC</span> phải có tên là <span style="color: red;">your-phone</span><br>
                                        Chú ý: Khi sử dụng Contact Form 7 và NinjaForms 3.x.x thì lấy dữ liệu field bằng cách %%{tên_field}%%<br>
                                        Ví dụ trong form có trường nhập tên là your-name thì trong tin nhắn muốn hiển thị tên sẽ là %%your-name%%<br>
                                        Tương tự lấy trường email khi name="your-email"  thì viết là %%your-email%%<br>
                                        Với NinjaForms bản cũ (2.9.x) thì bắt buộc phải điền ID của field số điện thoại và lấy trường dữ liệu bằng ID dạng %%{ID_FIELD}%%<br>
                                        Ví dụ trường tên có id là 5 thì lấy trong tin nhắn là %%5%%
                                    </small>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <script type="text/html" id="tmpl-devvn-tresms">
                            <tr>
                                <td>
                                    <textarea name="<?php echo $this->_optionName ?>[mess_content_list][id_{{data.stt}}][content]"></textarea>
                                    <p>
                                        <label><input type="checkbox" name="<?php echo $this->_optionName ?>[mess_content_list][id_{{data.stt}}][send_admin]" value="1"> Gửi tin nhắn cho admin</label>
                                    </p>
                                    <p>
                                        <textarea name="<?php echo $this->_optionName ?>[mess_content_list][id_{{data.stt}}][content_send_admin]" placeholder="Nội dung tin nhắn cho admin"></textarea>
                                    </p>
                                </td>
                                <td><select name="<?php echo $this->_optionName ?>[mess_content_list][id_{{data.stt}}][formID]">
                                        <option value=""><?php _e('Chọn Form','aathuat-code');?></option>
                                        <?php
                                        if($ninjsSelect){
                                            echo '<optgroup label="NinjaForms">';
                                            foreach ($ninjsSelect as $k=>$v){
                                                echo '<option value="'.$k.'">'.$v.'</option>';
                                            }
                                            echo '</optgroup>';
                                        }
                                        if($cf7Select){
                                            echo '<optgroup label="Contact Form 7">';
                                            foreach ($cf7Select as $k=>$v){
                                                echo '<option value="'.$k.'">'.$v.'</option>';
                                            }
                                            echo '</optgroup>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td><input value="" name="<?php echo $this->_optionName ?>[mess_content_list][id_{{data.stt}}][field_sdt_id]" type="number"/></td>
                                <td><input type="button" class="button AATHUAT_button AATHUAT_delete_esms" value="<?php _e('Xóa','aathuat-code');?>"></td>
                            </tr>
                        </script>
                        <?php };?>
                        <?php
                        if (
                            in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )
                        ){
                        ?>
<h2>Cài đặt tin nhắn cho Woocommerce</h2>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="enable_woo"><?php _e('Kích hoạt SMS cho Woocommerce', 'aathuat-code') ?></label>
                                    </th>
                                    <td>
<input type="checkbox" name="<?php echo $this->_optionName ?>[enable_woo]" id="enable_woo" value="1" <?php checked('1',intval($thuesms_settings['enable_woo']), true) ; ?>/> Kích hoạt gửi tin nhắn cho Woocommerce
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Nội dung tin nhắn', 'aathuat-code') ?></th>
                                    <td>
                                        <table class="woo_setting_mess">
                                            <tbody>
                                                <tr>
                                                    <td>
<label><input type="checkbox" name="<?php echo $this->_optionName ?>[account_creat]" id="account_creat" value="1" <?php checked('1',intval($thuesms_settings['account_creat']), true) ; ?>/> Gửi tin nhắn sau khi tạo tài khoản mới</label><br>
<textarea placeholder="Nội dung tin nhắn" name="<?php echo $this->_optionName ?>[account_creat_mess]"><?php echo sanitize_textarea_field($thuesms_settings['account_creat_mess'])?></textarea>
                                                        <small>Hiển thị TÊN bằng <span style="color: red;">%%name%%</span><br>
                                                            Khi checkout - bắt buộc phải có số điện thoại - billing_phone</small>
                                                    </td>
                                                    <td>
<label><input type="checkbox" name="<?php echo $this->_optionName ?>[order_creat]" id="order_creat" value="1" <?php checked('1',intval($thuesms_settings['order_creat']), true) ; ?>/> Gửi tin nhắn khi có đơn hàng mới</label><br>
<textarea placeholder="Nội dung tin nhắn" name="<?php echo $this->_optionName ?>[order_creat_mess]"><?php echo sanitize_textarea_field($thuesms_settings['order_creat_mess'])?></textarea>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>
<label><input type="checkbox" name="<?php echo $this->_optionName ?>[woo_status_complete]" id="woo_status_complete" value="1" <?php checked('1',intval($thuesms_settings['woo_status_complete']), true) ; ?>/> Gửi tin nhắn khi đơn hàng đã hoàn thành (Complete)</label><br>
                                                        <textarea placeholder="Nội dung tin nhắn" name="<?php echo $this->_optionName ?>[woo_status_complete_mess]"><?php echo sanitize_textarea_field($thuesms_settings['woo_status_complete_mess'])?></textarea>
                                                    </td>
                                                    <td>
<label><input type="checkbox" name="<?php echo $this->_optionName ?>[woo_status_processing]" id="woo_status_processing" value="1" <?php checked('1',intval($thuesms_settings['woo_status_processing']), true) ; ?>/> Gửi tin nhắn khi đơn hàng ở trạng thái đang xử lý (Processing)</label><br>
                                                        <textarea placeholder="Nội dung tin nhắn" name="<?php echo $this->_optionName ?>[woo_status_processing_mess]"><?php echo sanitize_textarea_field($thuesms_settings['woo_status_processing_mess'])?></textarea>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>
<label><input type="checkbox" name="<?php echo $this->_optionName ?>[woo_status_cancelled]" id="woo_status_cancelled" value="1" <?php checked('1',intval($thuesms_settings['woo_status_cancelled']), true) ; ?>/> Gửi tin nhắn khi HỦY đơn hàng (Cancelled)</label><br>
                                                        <textarea placeholder="Nội dung tin nhắn" name="<?php echo $this->_optionName ?>[woo_status_cancelled_mess]"><?php echo sanitize_textarea_field($thuesms_settings['woo_status_cancelled_mess'])?></textarea>
                                                    </td>
                                                    <td>
<label><input type="checkbox" name="<?php echo $this->_optionName ?>[order_creat_admin]" id="order_creat_admin" value="1" <?php checked('1',intval($thuesms_settings['order_creat_admin']), true) ; ?>/> Gửi tin nhắn cho admin khi có đơn hàng mới</label><br>
                                                        <textarea placeholder="Nội dung tin nhắn" name="<?php echo $this->_optionName ?>[order_creat_admin_mess]"><?php echo sanitize_textarea_field($thuesms_settings['order_creat_admin_mess'])?></textarea>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <div class="desc_woo_devvn">
    Hiển thị MÃ ĐƠN HÀNG bằng <span style="color: red;">%%orderid%%</span><br>
    Hiển thị firstName bằng <span style="color: red;">%%firstName%%</span><br>
    Hiển thị lastName bằng <span style="color: red;">%%lastName%%</span><br>
    Hiển thị tổng tiền bằng <span style="color: red;">%%total%%</span><br>
    Hiển thị số điện thoại khách hàng bằng <span style="color: red;">%%phone%%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <?php }?>
                        <div class="setting_typesms">
                            <h2>Cài đặt API <a target="_blank" href="https://thuesms.com/home-api-v2/" style="font-size:10px">Lấy ApiKey</a></h2>
                        </div>
                        <div class="type_api_table" id="type_api_esms">
                            <table class="form-table">
                                <tbody>
                                <tr>
                                    <th scope="row"><label for="apikey"><?php _e('ApiKey', 'aathuat-code') ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="<?php echo $this->_optionName ?>[apikey]" id="apikey" value="<?php echo $thuesms_settings['apikey'];?>"/>
                                    </td>
                                </tr>
                                <!--tr>
                                    <th scope="row"><label for="secretkey"><?php _e('SecretKey', 'aathuat-code') ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="<?php echo $this->_optionName ?>[secretkey]" id="secretkey" value="<?php echo $thuesms_settings['secretkey'];?>"/>
                                    </td>
                                </tr-->
                                <tr>
                                    <th scope="row"><label for="smstype"><?php _e('Loại tin nhắn (smsType)', 'aathuat-code') ?></label>
                                    </th>
                                    <td>
                                        <select name="<?php echo $this->_optionName ?>[smstype]" id="smstype">
                                            <option value="1" <?php selected(1,$thuesms_settings['smstype'],true);?>><?php _e('1- Brandname quảng cáo','aathuat-code');?></option>
                                            <option value="2" <?php selected(2,$thuesms_settings['smstype'],true);?>><?php _e('2 - Brandname chăm sóc khách hàng','aathuat-code');?></option>
                                            <option hidden value="3" <?php selected(3,$thuesms_settings['smstype'],true);?>><?php _e('3 - Đầu số ngẫu nhiên: dùng cho quảng cáo, tốc độ thấp','aathuat-code');?></option>
                                            <option hidden value="4" disabled="disabled" <?php selected(4,$thuesms_settings['smstype'],true);?>><?php _e('4 - Đầu số cố định Notify dùng cho cả quảng cáo và chăm sóc khách hàng','aathuat-code');?></option>
                                            <option hidden value="6" <?php selected(6,$thuesms_settings['smstype'],true);?>><?php _e('6 - Đầu số cố định Verify dùng cho chăm sóc khách hang, mã xác thực','aathuat-code');?></option>
                                            <option hidden value="7" <?php selected(7,$thuesms_settings['smstype'],true);?>><?php _e('7 - OTP: tin nhắn tốc độ cao, đầu số ngẫu nhiên','aathuat-code');?></option>
                                            <option value="8" <?php selected(8,$thuesms_settings['smstype'],true);?>><?php _e('8 - Cần đăng ký - Tin nhắn đầu số cố định 10 số, chuyên dùng cho chăm sóc khách hàng.','aathuat-code');?></option>
                                            <option hidden value="13" <?php selected(13,$thuesms_settings['smstype'],true);?>><?php _e('13 - Tin nhắn 2 chiều: cho phép khách hang trả lời lại(không được phép gửi tin quảng cáo)','aathuat-code');?></option>
                                        </select>
                                        <br><small><?php _e('Là loại tin nhắn muốn sử dụng, mỗi loại sẽ có đầu số hiển thị khác nhau và chi phí khác nhau. Vui lòng liên hệ hotline 0878549999 để được tư vấn cụ thể hơn','aathuat-code');?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="is_unicode"><?php _e('Tin nhắn có dấu (IsUnicode)', 'aathuat-code') ?></label>
                                    </th>
                                    <td>
                                        <select name="<?php echo $this->_optionName ?>[is_unicode]" id="is_unicode">
<option value="0" <?php selected(0,$thuesms_settings['is_unicode'],true);?>><?php _e('Gửi tin nhắn có dấu (65 Character)','aathuat-code');?></option>
<option value="1" <?php selected(1,$thuesms_settings['is_unicode'],true);?>><?php _e('Gửi tin nhắn KHÔNG có dấu (155 ký tự)','aathuat-code');?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="brandname"><?php _e('Brandname', 'aathuat-code') ?></label>
                                    </th>
                                    <td>
<input type="text" name="<?php echo $this->_optionName ?>[brandname]" id="brandname" value="<?php echo $thuesms_settings['brandname'];?>"/><br>
<small>Thay <b>ThueSMS</b> mặc định. Muốn sử dụng Brandname phải đăng ký với <a target="_blank" href="https://www.facebook.com/messages/t/ThueSMS">ThueSMS </a> và chọn smsType là 1, 2 hoặc 3 tùy vào lúc đăng ký</small>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="sandbox"><?php _e('Sandbox', 'aathuat-code') ?></label>
                                    </th>
                                    <td>
                                        <select name="<?php echo $this->_optionName ?>[sandbox]" id="sandbox">
	<option value="0" <?php selected(0,$thuesms_settings['sandbox'],true);?>><?php _e('0 - Không thử nghiệm, gửi tin đi thật','aathuat-code');?></option>
	<option value="1" <?php selected(1,$thuesms_settings['sandbox'],true);?>><?php _e('1 - Thử nghiệm (tin không đi mà chỉ tạo ra tin nhắn)','aathuat-code');?></option>
                                        </select>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php do_settings_fields('thuesms-options-group', 'default'); ?>
                        <?php do_settings_sections('thuesms-options-group', 'default'); ?>
                        <?php submit_button(); ?>
                    </form>
                </div>
                <?php
            }
            function sms_cf7_str_replace($sms_mess =  '', $cf7_data = array()){
                if(!$sms_mess || !is_array($cf7_data) || empty($cf7_data)) return $sms_mess;
                preg_match_all ( '/%%(\S*)%%/' , $sms_mess , $matches );
                foreach($matches[1] as $m){
                    $pattern = "/%%".$m."%%/";
                    $this_value = (isset($cf7_data[$m]) && $cf7_data[$m]) ? esc_attr(htmlspecialchars($cf7_data[$m])) : '';
                    $sms_mess = preg_replace( $pattern, $this_value, $sms_mess);
                }
                return $sms_mess;
            }
            function process_contact_form_data( $cf7 ){
                global $thuesms_settings;
                $list_mess = $thuesms_settings['mess_content_list'];
                $admin_phone = $thuesms_settings['admin_phone'];
                $mess_sent = $mess_sent_admin = false;
                if ($thuesms_settings['kichhoat'] && $list_mess && !empty($list_mess) && !isset($cf7->posted_data) && class_exists('WPCF7_Submission')) {
                    $submission = WPCF7_Submission::get_instance();
                    if ($submission) {
                        $post_data = $submission->get_posted_data();
                        $contact_form = $submission->get_contact_form();

                        $phone = (isset($post_data['your-phone']) && $post_data['your-phone']) ? esc_attr(htmlspecialchars($post_data['your-phone'])) : '';

                        $_wpcf7ID = intval($post_data['_wpcf7']);
                        if($_wpcf7ID == 0)
                            $_wpcf7ID=intval($contact_form->__get('id'));
                        $_wpcf7ID = 'cf7_'.$_wpcf7ID;
                        foreach ($list_mess as $mess) {
                            $content = isset($mess['content']) ? esc_textarea($mess['content']) : '';
                            $content = $this->sms_cf7_str_replace($content, $post_data);
                            $formID = isset($mess['formID']) ? $mess['formID'] : '';
                            $send_admin = isset($mess['send_admin']) ? $mess['send_admin'] : '';
                            $content_send_admin = isset($mess['content_send_admin']) ? esc_textarea($mess['content_send_admin']) : '';
                            $content_send_admin = $this->sms_cf7_str_replace($content_send_admin, $post_data);
                            if ($_wpcf7ID == $formID) {
                                if($phone && $content && !$mess_sent) {
                                    $this->send_esms($phone, $content);
                                    $mess_sent = true;
                                }
                                if($admin_phone && $content_send_admin && $send_admin && !$mess_sent_admin){
                                    $this->send_esms($admin_phone, $content_send_admin);
                                    $mess_sent_admin = true;
                                }
                            }
                        }
                    }
                }
                return true;
            }
            function sms_ninja_str_replace($sms_mess = '', $ninja_data = array()){
                if(!$sms_mess || !is_array($ninja_data) || empty($ninja_data)) return $sms_mess;
                preg_match_all ( '/%%(\S*)%%/' , $sms_mess , $matches );
                foreach($matches[1] as $m){
                    $pattern = "/%%".$m."%%/";
                    $this_val = (isset($ninja_data[$m])) ? esc_attr(htmlspecialchars($ninja_data[$m])) : '';
                    $sms_mess = preg_replace( $pattern, $this_val, $sms_mess);
                }
                return $sms_mess;
            }
            function process_ninjaform_data( $form_data ){
                global $thuesms_settings;
                $list_mess = $thuesms_settings['mess_content_list'];
                $admin_phone = $thuesms_settings['admin_phone'];
                if($thuesms_settings['kichhoat'] && $list_mess && !empty($list_mess)) {
                    $form_fields = $form_data['fields'];
                    $form_id = 'ninja_'.$form_data['form_id'];
                    $mess_sent = $mess_sent_admin = false;
                    foreach ($list_mess as $mess) {
                        $content = isset($mess['content']) ? esc_textarea($mess['content']) : '';
                        $formID = isset($mess['formID']) ? $mess['formID'] : '';
                        $send_admin = isset($mess['send_admin']) ? $mess['send_admin'] : '';
                        $content_send_admin = isset($mess['content_send_admin']) ? esc_textarea($mess['content_send_admin']) : '';
                        $your_phone = $your_name = $your_email = '';
                        $str_replace = array();
                        foreach ($form_fields as $field) {
                            $field_key = $field['key'];
                            $field_value = $field['value'];
                            if($field_key == 'your-phone') $your_phone = $field_value;
                            $str_replace[$field_key] = $field_value;
                        }
                        if ($your_phone && $content && $form_id ==  $formID && !$mess_sent) {
                            $content = $this->sms_ninja_str_replace($content, $str_replace);
                            $this->send_esms($your_phone, $content);
                            $mess_sent = true;
                        }
                        if ($admin_phone && $content_send_admin && $form_id ==  $formID && $send_admin && !$mess_sent_admin) {
                            $content_send_admin = $this->sms_ninja_str_replace($content_send_admin, $str_replace);
                            $this->send_esms($admin_phone, $content_send_admin);
                            $mess_sent_admin = true;
                        }
                    }
                }
            }
            function sms_ninja_old_str_replace($sms_mess = '', $ninja_data = array()){
                if(!$sms_mess || !is_array($ninja_data) || empty($ninja_data)) return $sms_mess;
                preg_match_all ( '/%%(\d*)%%/' , $sms_mess , $matches );
                foreach($matches[1] as $m){
                    $pattern = "/%%".$m."%%/";
                    $this_val = (isset($ninja_data[$m])) ? esc_attr(htmlspecialchars($ninja_data[$m])) : '';
                    $sms_mess = preg_replace( $pattern, $this_val, $sms_mess);
                }
                return $sms_mess;
            }
            function process_ninjaform_data_oldversion(){
                global $thuesms_settings, $ninja_forms_processing;
                $list_mess = $thuesms_settings['mess_content_list'];
                $admin_phone = $thuesms_settings['admin_phone'];
                $form_id = $ninja_forms_processing->get_form_ID();
                if($thuesms_settings['kichhoat'] && $list_mess && !empty($list_mess)) {
                    $field_data = $ninja_forms_processing->get_all_fields();
                    $form_id = 'ninja_'.$form_id;
                    $mess_sent = $mess_sent_admin = false;
                    foreach ($list_mess as $mess){
                        $content = isset($mess['content']) ? esc_textarea($mess['content']) : '';
                        $formID = isset($mess['formID']) ? $mess['formID'] : '';
                        $field_sdt_id = isset($mess['field_sdt_id']) ? $mess['field_sdt_id'] : '';
                        $send_admin = isset($mess['send_admin']) ? $mess['send_admin'] : '';
                        $content_send_admin = isset($mess['content_send_admin']) ? esc_textarea($mess['content_send_admin']) : '';

                        $your_phone = '';
                        $str_replace = array();
                        foreach ( $field_data as $field_id => $user_value ) {
                            if($field_sdt_id == $field_id){
                                $your_phone = $user_value;
                            }
                            $str_replace[$field_id] = $user_value;
                        }

                        if ($your_phone && $content && $form_id ==  $formID && !$mess_sent) {
                            $content = $this->sms_ninja_old_str_replace($content,$str_replace);
                            $this->send_esms($your_phone, $content);
                            $mess_sent = true;
                        }
                        if ($admin_phone && $content_send_admin && $form_id ==  $formID && $send_admin && !$mess_sent_admin) {
                            $content_send_admin = $this->sms_ninja_old_str_replace($content_send_admin,$str_replace);
                            $this->send_esms($admin_phone, $content_send_admin);
                            $mess_sent_admin = true;
                        }
                    }
                }
            }
            /*Start woo*/
            function sms_woocommerce_created_customer($customer_id, $new_customer_data){
                global $thuesms_settings;
                $account_creat = $thuesms_settings['account_creat'];
                $account_creat_mess = $thuesms_settings['account_creat_mess'];
                $billing_phone = get_user_meta( $customer_id, 'billing_phone', true );
                if ( isset( $_POST['billing_phone'] ) && !$billing_phone) {
                    $billing_phone = sanitize_text_field( $_POST['billing_phone'] );
                }
                if($account_creat && $account_creat_mess && $billing_phone) {
                    $account_creat_mess = str_replace('%%name%%', $new_customer_data['user_login'], $account_creat_mess);
                    $this->send_esms($billing_phone, $account_creat_mess);
                }
            }
            function sms_woo_string_replace($sms_mess = '', $order = '', $order_id = ''){
                if(!$sms_mess || !$order) return $sms_mess;

                if(!$order_id) $order_id = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->id : $order->get_id();

                $billing_first_name = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_first_name : $order->get_billing_first_name();
                if(!$billing_first_name) $billing_first_name = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : '';

                $billing_last_name = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_last_name : $order->get_billing_last_name();
                if(!$billing_last_name) $billing_last_name = isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : '';

                $billing_phone = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_phone : $order->get_billing_phone();
                if(!$billing_phone) $billing_phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';

                $total =  $order->get_total();
                if(!$total && version_compare( WC_VERSION, '2.7', '<' )) $total = WC()->cart->total;

                $str_replace['firstName'] = $billing_first_name;
                $str_replace['lastName'] = $billing_last_name;
                $str_replace['total'] = number_format($total);
                $str_replace['phone'] = $billing_phone;
                $str_replace['orderid'] = $order_id;

                preg_match_all ( '/%%(\w*)\%%/' , $sms_mess , $matches );
                foreach($matches[1] as $m){
                    $pattern = "/%%".$m."%%/";
                    $sms_mess = preg_replace( $pattern, $str_replace[$m], $sms_mess);
                }

                return $sms_mess;


            }
            function sms_woocommerce_new_order($orderID){
                global $thuesms_settings;
                $order_creat = $thuesms_settings['order_creat'];
                $order_creat_mess = $thuesms_settings['order_creat_mess'];
                $order_creat_admin = $thuesms_settings['order_creat_admin'];
                $order_creat_admin_mess = $thuesms_settings['order_creat_admin_mess'];
                $admin_phone = $thuesms_settings['admin_phone'];

                $order = wc_get_order( $orderID );

                $billing_phone = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_phone : $order->get_billing_phone();

                if(!$billing_phone) $billing_phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';

                if($billing_phone && $order_creat && $order_creat_mess) {
                    $order_creat_mess = $this->sms_woo_string_replace($order_creat_mess, $order);
                    $this->send_esms($billing_phone, $order_creat_mess);
                }
                if($admin_phone && $order_creat_admin && $order_creat_admin_mess){
                    $order_creat_mess = $this->sms_woo_string_replace($order_creat_admin_mess, $order);
                    $this->send_esms($admin_phone, $order_creat_mess);
                }
            }
            function sms_woocommerce_order_status_changed($order_id, $tatus_from, $status_to){
                global $thuesms_settings;
                $order = wc_get_order( $order_id );
                $billing_phone = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_phone : $order->get_billing_phone();
                if($billing_phone):
                    switch($status_to):
                        case 'completed':
                            if($thuesms_settings['woo_status_complete'] && $thuesms_settings['woo_status_complete_mess']){
                                $order_creat_mess = $this->sms_woo_string_replace($thuesms_settings['woo_status_complete_mess'], $order);
                                $this->send_esms($billing_phone, $order_creat_mess);
                            }
                            break;
                        case 'processing':
                            if($thuesms_settings['woo_status_processing'] && $thuesms_settings['woo_status_processing_mess']){
                                $order_creat_mess = $this->sms_woo_string_replace($thuesms_settings['woo_status_processing_mess'], $order);
                                $this->send_esms($billing_phone, $order_creat_mess);
                            }
                            break;
                        case 'cancelled':
                            if($thuesms_settings['woo_status_cancelled'] && $thuesms_settings['woo_status_cancelled_mess']){
                                $order_creat_mess = $this->sms_woo_string_replace($thuesms_settings['woo_status_cancelled_mess'], $order);
                                $this->send_esms($billing_phone, $order_creat_mess);
                            }
                            break;
                    endswitch;
                endif;
            }
            function AATHUAT_validate_phone_field_process() {
                $billing_phone = filter_input(INPUT_POST, 'billing_phone');
                if ( ! (preg_match('/^0([0-9]{9,10})+$/D', $billing_phone )) ){
                    wc_add_notice( "Xin nhập đúng <strong>số điện thoại</strong> của bạn"  ,'error' );
                }
            }
            /*#Start woo*/
            private function send_esms($YourPhone = '', $Content = ''){
                global $thuesms_settings;
                if($YourPhone) {
                    $YourPhone = explode(",", $YourPhone);
                    if(is_array($YourPhone) && !empty($YourPhone)) {
                        foreach($YourPhone as $phone){
                            $result = $this->send_thuesms_single($phone, $Content);
                        }
                    }
                }
            }
            private function send_thuesms_single($YourPhone = '', $Content = '')
            {
                global $thuesms_settings;
                $APIKey = $thuesms_settings['apikey'];  $AATHUAT1 = trim($APIKey); 
            //    $SecretKey = $thuesms_settings['secretkey'];
                $SecretKey = '0878549999';
                $smstype = $thuesms_settings['smstype'];
                $is_unicode = $thuesms_settings['is_unicode'];
                $brandname = $thuesms_settings['brandname'];
                $sandbox = $thuesms_settings['sandbox'];

                if(!$YourPhone || !$Content || !$APIKey || !$SecretKey || !$smstype ) return false;

                if($is_unicode == 1){
                    $Content = remove_accents($Content);
                    $is_unicode_convert = 0;
                }else{
                    $is_unicode_convert = 1;
                }

                $SendContent = urlencode($Content);

//$params = "Phone=$YourPhone&ApiKey=$APIKey&SecretKey=$SecretKey&content=$SendContent&SmsType=$smstype&IsUnicode=$is_unicode_convert&Sandbox=$sandbox";
$params = "IsUnicode=$is_unicode_convert&api=$AATHUAT1&content=$SendContent";

                if(($smstype == 1 || $smstype == 2) && $brandname){
                    $params .= '&Brandname='.$brandname;
                } 
$data = "https://thuesms.com/api/api-send-sms/?to=$YourPhone&".$params;

                $resp = wp_remote_request($data);

                $http_code = wp_remote_retrieve_response_code( $resp );
                if($http_code != '200'){
                    $body = wp_remote_retrieve_body($resp);
                    $obj = json_decode($body, true);

                    $obj['data_request'] = $params;
                    return $obj;
                }

                return [
                    "HttpCode" => "$http_code",
                    "CodeResult" => "99",
                    "CountRegenerate" => "0",
                    "ErrorMessage" => "Call RestAPI error: ".json_encode($resp['response']),
                    "Params" => $params,
                    "Request" => $data,
                ];

            }
            private function get_balance_esms()
            {
                global $thuesms_settings;
                $APIKey = $thuesms_settings['apikey'];
    //            $SecretKey = $thuesms_settings['secretkey'];
                $SecretKey = '0878549999';
                if(!$APIKey || !$SecretKey) return false; $AATHUAT1 = trim($APIKey); 
                $data = "https://thuesms.com/api/get-balance/?api=$AATHUAT1";

                $resp = wp_remote_request($data);
                $http_code = wp_remote_retrieve_response_code( $resp );
                if($http_code == '200'){
                    $body = wp_remote_retrieve_body($resp);
                    $obj = json_decode($body, true);
                    if($obj['CodeResponse'] == 100) {
                        return $obj['Balance'];
                    }
                }
                return false;
            }
            public function admin_enqueue_scripts() {
                $current_screen = get_current_screen();
                if ( isset( $current_screen->base ) && $current_screen->base == 'settings_page_setting-thuesms' ) {
                    wp_enqueue_style('aathuat-code-admin-styles', plugins_url('/assets/css/admin-style.css', __FILE__), array(), $this->_version, 'all');
                    wp_enqueue_script('aathuat-code-admin-js', plugins_url('/assets/js/admin-jquery.js', __FILE__), array('jquery','wp-util'), $this->_version, true);
                    wp_localize_script('aathuat-code-admin-js', 'AATHUAT_esms', array(
                        'ajaxurl'       => admin_url('admin-ajax.php'),
                        'siteurl'       => home_url(),
                    ));
                }
            }
        }

        new thuesms_Class();
    }
}