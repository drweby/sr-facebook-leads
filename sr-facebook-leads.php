<?php
/**
 * Plugin Name: SR Facebook Leads Ads Integration
 * Description: This plugin registers Facebook lead forms submissions using Gravity Forms
 * Version: 1.1.0
 * Author: StartupRunner
 * Author URI: http://startuprunner.com
 * License: None
*/
 
defined('ABSPATH') or die('No script kiddies please!');


class SRFacebookLeads{
 
    private static $instance;
    
    private $graphApiUrl = "https://graph.facebook.com/v2.5";
    
    private $config = [];
 
    
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
 
        return self::$instance;
    }
 
    
    
    
    private function __construct()
    {
        
        // Init saved application config
        $this->config = get_option('opt_leads_config');
        
        
        // Add Settings page as Forms submenu
        add_filter( 'gform_addon_navigation', [$this, 'addSettingsPage']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles']);
        
        
        // Callback script for webhook
        add_action('admin_post_nopriv_pagewebhook', [$this, 'pageWebhookCalback']);
        add_action('admin_post_pagewebhook', [$this, 'pageWebhookCalback']);
        
        
        // Adding setting for mapping forms
        add_filter( 'gform_form_settings', [$this, 'formMappingSetting'], 10, 2 );
        add_filter( 'gform_pre_form_settings_save', [$this, 'saveFormMappingSetting']);
        
        
        // Adding setting for mapping fields
        add_action( 'gform_field_advanced_settings', [$this, 'fieldMappingSetting'], 10, 2 );
        //Action to inject supporting script to the form editor page
        add_action( 'gform_editor_js', [$this, 'scriptFieldMappingSetting']);
        
    }
 
    
    
    
    public function fieldMappingSetting($position, $form_id) {
        //create settings on position 50 (right after Admin Label)
        if ($position == 50){
            $leadgenFields = $this->getLeadgenFields();
            $options = '';
            foreach($leadgenFields['data'] as $leadgenField){
                $options .= '<option value="'.$leadgenField['field_key'].'" >'.$leadgenField['label'].'</option>';
            }
            
            ?>
            <li class="mapped_lead_field field_setting">
                <label for="field_mapped_lead_field">
                    Mapped Lead Field
                </label>
                <select id="field_mapped_lead_field" onchange="SetFieldProperty('mappedLeadField', this.value)">
                    <option value=""></option>
                    <?php echo $options; ?>
                </select>
            </li>
            <?php
        }
    }
    
    public function scriptFieldMappingSetting(){
        ?>
        <script type='text/javascript'>
            //adding setting to fields of type "text"
            console.log('112233', fieldSettings);
            fieldSettings["text"] += ", .mapped_lead_field";
            fieldSettings["number"] += ", .mapped_lead_field";
            fieldSettings["hidden"] += ", .mapped_lead_field";
            fieldSettings["email"] += ", .mapped_lead_field";
            fieldSettings["phone"] += ", .mapped_lead_field";
            fieldSettings["website"] += ", .mapped_lead_field";
    
            //binding to the load field settings event to initialize the checkbox
            jQuery(document).bind("gform_load_field_settings", function(event, field, form){
                jQuery("#field_mapped_lead_field").val(field["mappedLeadField"]);
            });
        </script>
        <?php
    }
    
    
    
    
    public function formMappingSetting($settings, $form){
        
        $leadgenForms = $this->getLeadgenForms();
        $options = '';
        $currentValue = rgar($form, 'mapped_lead_form');
        
        foreach($leadgenForms['data'] as $leadgenForm){
            if(in_array($leadgenForm['id'], $currentValue)){
                $selected = 'selected';
            }else{
                $selected = '';
            }
            $options .= '<option value="'.$leadgenForm['id'].'" '.$selected.'>'.$leadgenForm['name'].'</option>';
        }
        
        $settings['Form Basics']['mapped_lead_form_setting'] = '
            <tr>
                <th><label for="mapped_lead_form">Mapped lead form</label></th>
                <td>
                    <select name="mapped_lead_form[]" multiple size="12">
                        <option value=""></option>
                        '.$options.'
                    </select>
                </td>
                
            </tr>';
        return $settings;
    }
    
    
    public function saveFormMappingSetting($form) {
        
        $form['mapped_lead_form'] = rgpost('mapped_lead_form');
        return $form;
    }
    
    
    
    
    public function getLeadgenFields(){
     
        if(!empty($this->config)){
            $queryUrl = $this->graphApiUrl.'/'.$this->config['pageId'].'/leadgen_qualifiers?access_token='.$this->getPageToken();
            $ret = json_decode(file_get_contents($queryUrl), true);
            if(!empty($ret)) return $ret;
        }
        return [];
    }
    
    
    
    
    private function getPageToken(){
        $queryUrl = $this->graphApiUrl.'/'.$this->config['pageId'].'?fields=access_token&access_token='.$this->config['accessToken'];
        $ret = json_decode(file_get_contents($queryUrl), true);
        if(!empty($ret)) return $ret['access_token'];
        return '';
    }
    
    
    
    
    public function getLeadgenForms(){
        if(!empty($this->config)){
            $queryUrl = $this->graphApiUrl.'/'.$this->config['pageId'].'/leadgen_forms?access_token='.$this->config['accessToken'];
            $ret = json_decode(file_get_contents($queryUrl), true);
            if(!empty($ret)) return $ret;
        }
        return [];
    }
    
    
    
    
    public function addSettingsPage($menu_items){
        
        $menu_items[] = array( 
            "name" => "facebook_leads", 
            "label" => "Facebook Leads", 
            "callback" => function(){
                
                if(isset($_POST['leads_app_settings_submit'])){
                    
                    $this->config = $_POST['conf'];
                    
                    update_option('opt_leads_config', $this->config);
                    $updatedConfirm = 'Config has been saved';
                }
                
                require('templates/settings-form.php');
                
            }, 
            "permission" => "manage_options" 
        );
        return $menu_items;
    }
   
    
    
    
    public function addAdminStyles($hook){
        if($hook == 'forms1_page_facebook_leads'){
            wp_enqueue_style('fb-leads-styles', plugin_dir_url( __FILE__ ) . 'styles/styles.css' );
        }
    }
    
    
    
    
    public function pageWebhookCalback(){
        if(isset($_REQUEST['hub_challenge']) && isset($_REQUEST['hub_verify_token'])){
            $challenge = $_REQUEST['hub_challenge'];
            $verify_token = $_REQUEST['hub_verify_token'];
            
            if ($verify_token === 'srclbvrftkn') {
                echo $challenge;
                exit;
            }
        }
        
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Save input for debug
        //file_put_contents(get_home_path().'logs/out_'.time().'.txt', print_r($input, true));
        
        if(empty($this->config) || empty($input) || $input['object'] != 'page'){
            return;
        }
        
        foreach($input['entry'] as $entry){
            if($entry['id'] == $this->config['pageId']){
                foreach($entry['changes'] as $change){
                    if($change['field'] == 'leadgen'){
                        $leadFormId = $change['value']['form_id'];
                        $leadgenId = $change['value']['leadgen_id'];
                        if(null != $gFormId = $this->getMappedForm($leadFormId)){
                            if($leadUserInfo = $this->getLeadUserInfo($leadgenId)){
                                $this->addGFEntry($leadUserInfo, $gFormId);
                            }
                        }
                    }
                }
            }
        }
    }


    
    
    private function getMappedForm($leadFormId){
        
        $gForms = GFAPI::get_forms();
        
        foreach($gForms as $gForm){
            if(isset($gForm['mapped_lead_form']) && in_array($leadFormId, $gForm['mapped_lead_form'])){
                return $gForm['id'];
            }
        }
        return null;
    }
    
    
    
    
    private function getLeadUserInfo($leadgenId){
        $requestUrl = $this->graphApiUrl."/".$leadgenId."?access_token=".$this->config['accessToken'];
        $result = json_decode(file_get_contents($requestUrl), true);
        return $result;
    }
    
    
    
    
    private function getMappedFieldId($leadFieldName, $gFormId){
        
        $gForm = GFAPI::get_form($gFormId);
        
        foreach($gForm['fields'] as $gFormField){
            if(isset($gFormField['mappedLeadField']) && $gFormField['mappedLeadField'] == $leadFieldName){
                return $gFormField['id'];
            }
        }
        
        return null;
    }
    
    
    
    
    // Manually create entries and send notifications with Gravity Forms
    private function addGFEntry($leadUserInfo, $gFormId){
        // add entry
        $entry = array("form_id" => $gFormId);
        foreach($leadUserInfo['field_data'] as $leadField){
            if(null != $mappedFieldId = $this->getMappedFieldId($leadField['name'], $gFormId)){
                $entry[$mappedFieldId] = $leadField['values'][0];
            }
        } 
        
        
        $entryId = GFAPI::add_entry($entry);
        
        // send notifications
        $this->sendNotifications($gFormId, $entryId);
        
    }
    
    
    
    
    private function sendNotifications($form_id, $entry_id){
        // Get the array info for our forms and entries
        // that we need to send notifications for
    
        $form = RGFormsModel::get_form_meta($form_id);
        $entry = RGFormsModel::get_lead($entry_id);
    
        // Loop through all the notifications for the
        // form so we know which ones to send
    
        $notification_ids = array();
    
        foreach($form['notifications'] as $id => $info){
    
            array_push($notification_ids, $id);
    
        }
    
        // Send the notifications
    
        GFCommon::send_notifications($notification_ids, $form, $entry);
    
    }
    
}

SRFacebookLeads::getInstance();

