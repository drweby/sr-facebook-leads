<div class="wrap">
    <h1>Facebook Leads Integration</h1>
    
    <div class="left_block">
        <h2>Current Config</h2>
        <?php if(!empty($updatedConfirm)){?>
            <div class="confirm"><?php echo $updatedConfirm ?></div>
        <?php }?>
        
        <?php if(!empty($this->config)){ ?>
            <div class="form_row">
                <h4>Application</h4>
                <code><?php echo $this->config['appName']?> (<?php echo $this->config['appId']?>)</code>
            </div>
            <div class="form_row">
                <h4>Subscribed Page</h4>
                <code><?php echo $this->config['pageName']?> (<?php echo $this->config['pageId']?>)</code>
            </div>
        <?php } ?>
        
    </div>
    <div class="right_block">
        <h2>Callback Script Data</h2>
        <div class="form_row">
            <label for="callback_url">URL</label><input id="callback_url" size="50" value="<?php echo admin_url('admin-post.php');?>?action=pagewebhook" readonly>
        </div>
        <div class="form_row">
            <label for="verify_tkn">Verify Token</label><input id="verify_tkn" value="srclbvrftkn" readonly>
        </div>
        <h2>Config Wizard</h2>
        <form id="cfgForm" method="post" action="">
            <input type="hidden" name="leads_app_settings_submit" value="Y">
            <div class="form_row">
                <label for="fld_lead_app_id">App Id</label>
                <input id="fld_lead_app_id" value="">
            </div>
            <div class="form_row">
                <label for="fld_lead_app_secret">App Secret</label>
                <input id="fld_lead_app_secret" value="">
            </div>
            <div class="form_row" id="list_btn_block">
                <br>
                <button class="button" id="listBtn">List available pages</button>
            </div>
            <div class="form_row" id="pages_block">
                <h3>Choose a page that should be subscribed to the App</h3> 
                <select id="pages_list">
                    <option value=""></option>
                </select>
            </div>
            <div class="form_row" id="save_conf_block">
                <button class="button button-primary" id="save_conf_btn">Save Config</button>
            </div>
        </form>
    </div>
    <div id="manual">
        <h2>Basic steps to create new integration</h2>
        <ol>
            <li>
                Add new FB Application for current site
                <ul>
                    <li>Login Facebook under user having full access to needed Pages and their Lead forms</li>
                    <li>Go to https://developers.facebook.com/ and add new Website App (Category = Apps for pages). Specify name and skip any quick starters.</li>
                    <li>On the created App dashboard go to Webhooks section and create new Subscription (type=Page)</li>
                    <li>
                        <ul>
                            <li><i>Callback URL</i> = URL from Callback Script Data (see above on this page)</li>
                            <li><i>Verify Token</i> = Verify Token from Callback Script Data (see above on this page)</li>
                            <li><i>Subscription Fields</i> - check <i>leadgen</i></li>
                        </ul>
                    </li>
                    <li>Click <i>Verify and Save</i> button</li>
                    <li>Go to App Dashboard->Settings and provide App Domains(example:bizhive.com), click Add Platform->Website and provide Site URL (example:https://bizhive.com)</li>
                </ul>
            </li>
            <li>
                Generate config for callback script using Config Wizard
                <ul>
                    <li>Fill in <i>App Id</i> and <i>App Secret</i> fields and click button <i>List Available Pages</i></li>
                    <li>Choose Page you want to subscribe to the App, then click Save Config</li>
                    <li>After saving config all new leads confirmations from selected page will go to the callback script</li>
                </ul>
            </li>
            <li>
                Specify mapped forms and field
                <ul>
                    <li>Each Gravity Form on it's Form Settings page has setting Mapped lead form. Use it for subscribing on existing Lead Form</li>
                    <li>Each Gravity Form field has setting Mapped Lead Field under Advanced tab. Use it for subscribing on particular Lead Form field</li>
                </ul>
            </li>
        </ol>
    </div>
</div>

<script>

jQuery(function($){
    var conf = {
        "appId": '',
        "appSecret": '',
        "appName": '',
        "accessToken": '',
        "pageAccessToken": '',
        "pageId": '',
        "pageName": ''
    }
        
    $("#listBtn").click(function(e){
        e.preventDefault();
        var appId = $("#fld_lead_app_id").val();
        var appSecret = $("#fld_lead_app_secret").val();
        if(appId != '' && appSecret != ''){
            conf.appId = appId;
            conf.appSecret = appSecret;
            initWizard();
        }else{
            alert("Please provide App Id and App Secret fields");
        }
        
    });
    
    
    
    $("#save_conf_btn").click(function(e){
        e.preventDefault();
        
        for(var k in conf){
            $('<input>')
                .attr('type', 'hidden')
                .attr('name', 'conf['+k+']')
                .val(conf[k])
                .prependTo($("#cfgForm"));
        };
        $("#cfgForm").submit();
    });
    
    
    
    function initWizard(){
        FB.init({
            appId      : conf.appId,
            xfbml      : true,
            version    : 'v2.5'
        });
        
        
        FB.login(function(response) {
            if (response.authResponse) {
                console.log('Success logged ', response);
                
                getAppName();
                
                getAccessToken(response.authResponse.accessToken);
                
                listAvailablePages();
                
            } else {
             alert('User cancelled login or did not fully authorize.');
            }
        }, {scope: 'manage_pages,ads_read'});
    }
    
    
    function getAppName(){
        FB.api(conf.appId, function(response) {
            if(typeof response.error != 'undefined'){
                console.log('ERROR', response);
                alert(response.error.message);
            }else{
                conf.appName = response.name;
            }
        });
    }
    
    
    
    function getAccessToken(shortLivedToken){
        $.get(
            'https://graph.facebook.com/oauth/access_token',
            {
                'client_id': conf.appId,
                'client_secret': conf.appSecret,
                'grant_type': 'fb_exchange_token',
                'fb_exchange_token': shortLivedToken
            }
        )
        .done(function(tokenResp){
            console.log('Access Token received:', tokenResp);
            conf.accessToken = tokenResp.replace('access_token=', '');
        })
        .fail(function(){alert('Getting token error')});
    }
    
    
    
    function listAvailablePages(){
        FB.api('/me/accounts', {"access_token":conf.accessToken}, function(response) {
            if(typeof response.error != 'undefined'){
                console.log('ERROR', response);
                alert(response.error.message);
            }else{
                var $select = $("#pages_list").change(function(){
                    conf.pageId = this.value;
                    conf.pageName = this.options[this.selectedIndex].text;
                    conf.pageAccessToken = this.options[this.selectedIndex].dataset.pageToken;
                    
                    subscribePage();
                    
                });
                response.data.forEach(function(v, i){
                    $('<option>')
                        .val(v.id)
                        .text(v.name)
                        .attr('data-page-token', v.access_token)
                        .appendTo($select);
                });
                $('#list_btn_block').hide();
                $('#pages_block').show();
            }
        });
    }
    
    
    
    function subscribePage(){
        FB.api(
            '/' + conf.pageId + '/subscribed_apps',
            'post',
            {access_token: conf.pageAccessToken},
            function(response) {
                if(typeof response.error != 'undefined'){
                    console.log('ERROR', response);
                    alert(response.error.message);
                }else{
                    $("#save_conf_block").show();
                }
            }
        );
    }
    
});



// FB Graph API js SDK
(function(d, s, id){
var js, fjs = d.getElementsByTagName(s)[0];
if (d.getElementById(id)) {return;}
js = d.createElement(s); js.id = id;
js.src = "//connect.facebook.net/en_US/sdk.js";
fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));



</script>
