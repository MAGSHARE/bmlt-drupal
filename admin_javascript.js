/****************************************************************************************//**
* \file admin_javascript.js                                                                 *
* \brief The javascript for the BMLTPlugin class (Admin options).                           *
* \version 1.0.0                                                                            *
    
    This file is part of the Basic Meeting List Toolbox (BMLT).
    
    Find out more at: http://magshare.org/bmlt
    
    BMLT is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    
    BMLT is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    
    You should have received a copy of the GNU General Public License
    along with this code.  If not, see <http://www.gnu.org/licenses/>.
********************************************************************************************/

var g_BMLTPlugin_admin_main_map = null;         ///< This will hold the map instance.
var g_BMLTPlugin_admin_marker = null;           ///< This holds the marker object.
var g_BMLTPlugin_AjaxRequest = null;            ///< Used to stop server checks from stepping on each other.
var g_BMLTPlugin_oldBeforeUnload = null;        ///< We replace the window's beforeUnload with our prompt.
var g_BMLTPlugin_hold_the_pickles = false;      ///< This is set to avoid dirtifying the sheet.

/****************************************************************************************//**
*   \brief Returns the selected option index.                                               *
*                                                                                           *
*   \returns an integer.                                                                    *
********************************************************************************************/
function BMLTPlugin_GetSelectedOptionIndex()
{
    var option_select = document.getElementById ( 'BMLTPlugin_legend_select' );
    var option_index = 1;
    if ( option_select )
        {
        option_index = parseInt ( option_select.value );
        }
    
    return option_index;
}

/****************************************************************************************//**
*   \brief Hides "primer" text in text items.                                               *
********************************************************************************************/
function BMLTPlugin_ClickInText (   in_id,              ///< The ID of the item
                                    in_default_text,    ///< The "primer" text.
                                    in_blur             ///< If this is true, then we reverse the process.
                                    )
{
    var item = document.getElementById(in_id);
    
    if ( item && (item.type == 'text') )
        {
        var value = item.value.toString();
        
        if ( (in_blur != true) && value && (value == in_default_text) )
            {
            item.value = '';
            }
        else
            {
            if ( (in_blur == true) && !value )
                {
                item.value = in_default_text;
                };
            };
        };
}

/****************************************************************************************//**
*   \brief Switches the visibility of the property sheets -called when the select changes.  *
********************************************************************************************/
function BMLTPlugin_SelectOptionSheet ( in_value,       ///< The current value of the select
                                        in_num_options  ///< The number of available options.
                                        )
{
    for ( var i=1; i<=in_num_options; i++ )
        {
        var item_id = 'BMLTPlugin_option_sheet_'+i+'_div';
        var item = document.getElementById(item_id);
        if ( item )
            {
            item.style.display = ((i == in_value) ? 'block' : 'none');
            };
        };
    
    BMLTPlugin_admin_load_map();

    var indicator = document.getElementById ( 'BMLTPlugin_option_sheet_indicator_'+in_value );
    var version = document.getElementById ('BMLTPlugin_option_sheet_version_indicator_'+in_value );
    
    indicator.className = 'BMLTPlugin_option_sheet_NEUT';
    version.innerHTML = null;
};

/****************************************************************************************//**
*   \brief Reacts to a new language being selected in the popup menu.                       *
********************************************************************************************/
function BMLTPlugin_ChangeLanguage( )
{
    var option_index = BMLTPlugin_GetSelectedOptionIndex();
    var name_object = document.getElementById ( 'BMLTPlugin_option_sheet_language_name_'+option_index );
    
    if ( name_object )
        {
        var select_object = document.getElementById ( 'BMLTPlugin_option_sheet_language_'+option_index );
        
        if ( select_object )
            {
            name_object.value = select_object.options[select_object.selectedIndex].text;
            };
        };
    
    BMLTPlugin_DirtifyOptionSheet();
};

/****************************************************************************************//**
*   \brief Deletes one of the options after a confirm.                                      *
********************************************************************************************/
function BMLTPlugin_DeleteOptionSheet()
{
    // The c_g_delete_confirm_message is actually set in the PHP file. It is a constant global.
    if ( confirm ( c_g_delete_confirm_message ) )
        {
        var option_index = BMLTPlugin_GetSelectedOptionIndex();
        
        var url = document.getElementById ( 'BMLTPlugin_sheet_form' ).action + '&BMLTPlugin_delete_option=' + option_index;

        window.location.replace ( url );
        };
};

/****************************************************************************************//**
*   \brief This actually saves the new options.                                             *
********************************************************************************************/
function BMLTPlugin_SaveOptions()
{
    var url = document.getElementById ( 'BMLTPlugin_sheet_form' ).action;
    
    url += '&BMLTPlugin_Save_Settings_AJAX_Call=1&BMLTPlugin_set_options=1';
    
    for ( var option_index = 1; option_index <= c_g_BMLTPlugin_coords.length; option_index++ )
        {
        var name = document.getElementById ( 'BMLTPlugin_option_sheet_name_'+option_index ).value.toString();

        url += '&BMLTPlugin_option_sheet_name_'+option_index+'=';

        if ( name && (name != c_g_BMLTPlugin_no_name) )
            {
            url += encodeURIComponent ( name );
            };
        
        var root_server = document.getElementById ( 'BMLTPlugin_option_sheet_root_server_'+option_index ).value.toString();
        
        url += '&BMLTPlugin_option_sheet_root_server_'+option_index+'=';

        if ( root_server && (root_server != c_g_BMLTPlugin_no_root) )
            {
            url += encodeURIComponent ( root_server );
            };
        
        var new_search = document.getElementById ( 'BMLTPlugin_option_sheet_new_search_'+option_index ).value.toString();
        
        url += '&BMLTPlugin_option_sheet_new_search_'+option_index+'=';

        if ( new_search && (new_search != c_g_BMLTPlugin_no_search) )
            {

            url += encodeURIComponent ( new_search );
            };
        
        var lang_enum = document.getElementById ( 'BMLTPlugin_option_sheet_language_'+option_index ).value.toString();
        
         if ( lang_enum )
            {
            url += '&BMLTPlugin_option_sheet_language_'+option_index+'='+encodeURIComponent ( lang_enum );
            }
        
        var lang_name = document.getElementById ( 'BMLTPlugin_option_sheet_language_name_'+option_index ).value.toString();
        
         if ( lang_name )
            {
            url += '&BMLTPlugin_option_sheet_language_name_'+option_index+'='+encodeURIComponent ( lang_name );
            }
        
        var distance_units = document.getElementById ( 'BMLTPlugin_option_sheet_distance_units_'+option_index ).value.toString();
        
         if ( distance_units )
            {
            url += '&BMLTPlugin_option_sheet_distance_units_'+option_index+'='+encodeURIComponent ( distance_units );
            }
        
        var grace_period = document.getElementById ( 'BMLTPlugin_option_sheet_grace_period_'+option_index ).value.toString();
        
         if ( grace_period )
            {
            url += '&BMLTPlugin_option_sheet_grace_period_'+option_index+'='+encodeURIComponent ( grace_period );
            }
        
        var time_offset = document.getElementById ( 'BMLTPlugin_option_sheet_time_offset_'+option_index ).value.toString();
        
         if ( time_offset )
            {
            url += '&BMLTPlugin_option_sheet_time_offset_'+option_index+'='+encodeURIComponent ( time_offset );
            }
       
        var gmaps_key = document.getElementById ( 'BMLTPlugin_option_sheet_gkey_'+option_index ).value.toString();
         
        url += '&BMLTPlugin_option_sheet_gkey_'+option_index+'=';
        
        if ( gmaps_key && (gmaps_key != g_BMLTPlugin_no_gkey_string) )
            {
            url += encodeURIComponent ( gmaps_key );
            };
       
        var initial_view = document.getElementById ( 'BMLTPlugin_option_sheet_initial_view_'+option_index ).value.toString();
         
        url += '&BMLTPlugin_option_sheet_initial_view_'+option_index+'='+initial_view;
       
        var my_theme = document.getElementById ( 'BMLTPlugin_option_sheet_theme_'+option_index ).value.toString();
         
        url += '&BMLTPlugin_option_sheet_theme_'+option_index+'='+my_theme;
       
        var push_down = (document.getElementById ( 'BMLTPlugin_option_sheet_push_down_'+option_index ).checked ? '1' : '0');
         
        url += '&BMLTPlugin_option_sheet_push_down_'+option_index+'='+push_down;
       
        var additional_css = document.getElementById ( 'BMLTPlugin_option_sheet_additional_css_'+option_index ).value.toString();
         
        url += '&BMLTPlugin_option_sheet_additional_css_'+option_index+'=';
        
        if ( additional_css )
            {
            url += encodeURIComponent ( additional_css );
            };
    
        url += '&BMLTPlugin_option_latitude_'+option_index+'='+parseFloat(c_g_BMLTPlugin_coords[option_index-1].lat);
        url += '&BMLTPlugin_option_longitude_'+option_index+'='+parseFloat(c_g_BMLTPlugin_coords[option_index-1].lng);
        url += '&BMLTPlugin_option_zoom_'+option_index+'='+parseInt(c_g_BMLTPlugin_coords[option_index-1].zoom);
        };
    
    BMLTPlugin_AjaxRequest ( url, BMLTPlugin_SettingCallback, 'post' );
};

/****************************************************************************************//**
*   \brief AJAX callback for the settings.                                                  *
********************************************************************************************/
function BMLTPlugin_SettingCallback (in_success ///< The HTTPRequest object
                                    )
{
    var fader = document.getElementById ( 'BMLTPlugin_Fader' );
    
    if ( fader )
        {
        fader.style.opacity = 1;
        fader.FadeState = null;
        
        if ( parseInt(in_success.responseText) != 1 )
            {
            fader.innerHTML = c_g_BMLTPlugin_failure_message;
            fader.className = 'BMLTPlugin_Message_bar_fail';
            g_BMLTPlugin_TimeToFade = c_g_BMLTPlugin_failure_time;
            }
        else
            {
            fader.innerHTML = c_g_BMLTPlugin_success_message;
            fader.className = 'BMLTPlugin_Message_bar_success';
            g_BMLTPlugin_TimeToFade = c_g_BMLTPlugin_success_time;
            BMLTPlugin_DirtifyOptionSheet ( true );
            
            for ( var option_index = 1; option_index <= c_g_BMLTPlugin_coords.length; option_index++ )
                {
                var name = document.getElementById ( 'BMLTPlugin_option_sheet_name_'+option_index ).value.toString();
                
                if ( name && (name != c_g_BMLTPlugin_no_name) )
                    {
                    var option = document.getElementById ( 'BMLTPlugin_option_sel_'+option_index );
                    if ( !option )
                        {
                        option = document.getElementById ( 'BMLTPlugin_legend' );
                        };
                    option.innerHTML = name;
                    };
                };
            };
        
        BMLTPlugin_StartFader();
        };
};

/****************************************************************************************//**
*   \brief When a change is made to an option, this sets a "dirty" flag, by enabling the    *
*   "Save Changes" button.                                                                  *
********************************************************************************************/
function BMLTPlugin_DirtifyOptionSheet( in_disable  ///< If this is true, then we "clean" the flag.
                                        )
{
    var keyID = (window.event && window.event.keyCode) ? window.event.keyCode : null;

    if ( keyID != 9 )   // Don't react to tab.
        {
        document.getElementById ( 'BMLTPlugin_toolbar_button_save' ).disabled = (in_disable == true);
        document.getElementById ( 'BMLTPlugin_toolbar_button_save' ).className = 'BMLTPlugin_toolbar_button_save_' + ((in_disable == true) ? 'disabled' : 'enabled').toString();
    
        document.getElementById ( 'BMLTPlugin_toolbar_button_new' ).disabled = (in_disable != true);
        document.getElementById ( 'BMLTPlugin_toolbar_button_new' ).className = ((in_disable != true) ? 'BMLTPlugin_toolbar_button_save_disabled' : 'BMLTPlugin_create_button').toString();
        
        if ( document.getElementById ( 'BMLTPlugin_toolbar_button_del' ) )
            {
            document.getElementById ( 'BMLTPlugin_toolbar_button_del' ).disabled = (in_disable != true);
            document.getElementById ( 'BMLTPlugin_toolbar_button_del' ).className = ((in_disable != true) ? 'BMLTPlugin_toolbar_button_save_disabled' : 'BMLTPlugin_delete_button').toString();
            };
            
        if ( !document.getElementById ( 'BMLTPlugin_toolbar_button_save' ).disabled )
            {
            if ( window.onbeforeunload != BMLTPlugin_CloseHandler )
                {
                g_BMLTPlugin_oldBeforeUnload = window.onbeforeunload;
                window.onbeforeunload = BMLTPlugin_CloseHandler;
                };
            }
        else
            {
            window.onbeforeunload = g_BMLTPlugin_oldBeforeUnload;
            };
        };
};

/****************************************************************************************//**
*   \brief Fetches the available and default languages from the server, and sets up the     *
*   language dropdown accordingly.                                                          *
*                                                                                           *
********************************************************************************************/
function BMLTPlugin_FetchServerLangs ( in_id    ///< The index of the option to test.
                                        )
{
    var url = document.getElementById ( 'BMLTPlugin_sheet_form' ).action;
    var option_index = BMLTPlugin_GetSelectedOptionIndex();
    var throbber_item = document.getElementById('BMLTPlugin_option_sheet_Server_Lang_Throbber_'+option_index);
   
    url += '&BMLTPlugin_Fetch_Langs_AJAX_Call=1';
    
    var root_server = document.getElementById ( 'BMLTPlugin_option_sheet_root_server_'+option_index ).value.toString();
    
    if ( root_server && (root_server != c_g_BMLTPlugin_no_root) )
        {
        url += '&BMLTPlugin_AJAX_Call_Check_Root_URI='+encodeURIComponent ( root_server );
        };

    throbber_item.innerHTML = '<img src="'+c_g_BMLTPlugin_admin_google_map_images+'/small_throbber.gif" alt="AJAX Throbber" />';
    BMLTPlugin_AjaxRequest ( url, BMLTPlugin_FetchServerLangsCallback, 'get' );
};

/****************************************************************************************//**
*   \brief This is the AJAX callback for setting up the server languages.                   *
*                                                                                           *
********************************************************************************************/
function BMLTPlugin_FetchServerLangsCallback ( in_object  ///< The processing result. Should be an HTTPRequest with a JSON object in the text.
                                                )
{
    var option_index = BMLTPlugin_GetSelectedOptionIndex();
    var throbber_item = document.getElementById('BMLTPlugin_option_sheet_Server_Lang_Throbber_'+option_index);
    
    if ( in_object.responseText )
        {
        var select_item = document.getElementById('BMLTPlugin_option_sheet_language_'+option_index);
        
        if ( select_item )
            {
            var name_item = document.getElementById('BMLTPlugin_option_sheet_language_name_'+option_index);
            
            var old_enum = select_item.options[select_item.selectedIndex].value;
            
            if ( name_item )
                {
                eval ( 'var json_obj = '+in_object.responseText+';' );

                select_item.options.length = 0;
                
                for ( var c = 0; c < json_obj.length; c++ )
                    {
                    var lang_enum = json_obj[c][0];
                    var lang_name = json_obj[c][1];
                    var lang_default = json_obj[c][2];

                    select_item.options[c] = new Option ( lang_name.toString(), lang_enum.toString());

                    if ( lang_default )
                        {
                        select_item.selectedIndex = c;
                        };
                    };
                
                select_item.disabled = false;
                
                throbber_item.innerHTML = '';
                
                var new_enum = select_item.options[select_item.selectedIndex].value;
                
                if ( old_enum != new_enum )
                    {
                    BMLTPlugin_DirtifyOptionSheet();
                    };
               };
            };
        }
    else
        {
        throbber_item.innerHTML = c_g_BMLTPlugin_root_canal;
        };
};

/****************************************************************************************//**
*   \brief Puts up an "are you sure?" message if the dirty flag is set.                     *
*                                                                                           *
********************************************************************************************/
function BMLTPlugin_CloseHandler()
{
    return c_g_BMLTPlugin_unsaved_prompt;
};

/****************************************************************************************//**
*   \brief Starts the message "fader."                                                      *
*                                                                                           *
*   Simple fader, taken from here:                                                          *
*       http://www.switchonthecode.com/tutorials/javascript-tutorial-simple-fade-animation  *
********************************************************************************************/
function BMLTPlugin_StartFader()
{
    var eid = 'BMLTPlugin_Fader';
    var element = document.getElementById ( eid );
    
    if ( element )
        {
        if ( element.FadeState == null )
            {
            if ( element.style.opacity == null 
                || element.style.opacity == '' 
                || element.style.opacity == '1' )
                {
                element.FadeState = 2;
                }
            else
                {
                element.FadeState = -2;
                };
            };
        
        if ( element.FadeState == 1 || element.FadeState == -1 )
            {
            element.FadeState = element.FadeState == 1 ? -1 : 1;
            element.FadeTimeLeft = g_BMLTPlugin_TimeToFade - element.FadeTimeLeft;
            }
        else
            {
            element.FadeState = element.FadeState == 2 ? -1 : 1;
            element.FadeTimeLeft = g_BMLTPlugin_TimeToFade;
            setTimeout ( "BMLTPlugin_animateFade(" + new Date().getTime() + ",'" + eid + "')", 33);
            };
        };
};

/****************************************************************************************//**
*   \brief Animates the fade.                                                               *
*                                                                                           *
*   Simple fader, taken from here:                                                          *
*       http://www.switchonthecode.com/tutorials/javascript-tutorial-simple-fade-animation  *
********************************************************************************************/
function BMLTPlugin_animateFade (   lastTick,   ///< The time of the last tick.
                                    eid         ///< The element ID
                                )
{  
    var curTick = new Date().getTime();
    var elapsedTicks = curTick - lastTick;
    
    var element = document.getElementById ( eid );
    
    if ( element.FadeTimeLeft <= elapsedTicks )
        {
        element.style.opacity = element.FadeState == 1 ? '1' : '0';
        element.style.filter = 'alpha(opacity = ' + (element.FadeState == 1 ? '100' : '0') + ')';
        element.FadeState = element.FadeState == 1 ? 2 : -2;
        return;
        };
    
    element.FadeTimeLeft -= elapsedTicks;
    
    var newOpVal = element.FadeTimeLeft/g_BMLTPlugin_TimeToFade;
    
    if ( element.FadeState == 1 )
        {
        newOpVal = 1 - newOpVal;
        };
    
    element.style.opacity = newOpVal;
    element.style.filter = 'alpha(opacity = ' + (newOpVal*100) + ')';
    
    setTimeout ( "BMLTPlugin_animateFade(" + curTick + ",'" + eid + "')", 33 );
};

/****************************************************************************************//**
*   \brief Uses AJAX to test the given root server URI.                                     *
********************************************************************************************/
function BMLTPlugin_TestRootUri_call()
{
    var option_index = BMLTPlugin_GetSelectedOptionIndex();
    
    var url = document.getElementById ( 'BMLTPlugin_sheet_form' ).action + '&BMLTPlugin_AJAX_Call=1&BMLTPlugin_AJAX_Call_Check_Root_URI=';
    
    var root_server = document.getElementById ( 'BMLTPlugin_option_sheet_root_server_'+option_index ).value.toString();

    if ( root_server && (root_server != c_g_BMLTPlugin_no_root) )
        {
        url += encodeURIComponent ( root_server );
        };
    
    if ( g_BMLTPlugin_AjaxRequest )
        {
        g_BMLTPlugin_AjaxRequest = null;
        };
    
    var indicator = document.getElementById ( 'BMLTPlugin_option_sheet_indicator_'+option_index );
    indicator.className = 'BMLTPlugin_option_sheet_NEUT';
    var version = document.getElementById ('BMLTPlugin_option_sheet_version_indicator_'+option_index );
    version.innerHTML = '<img src="'+c_g_BMLTPlugin_admin_google_map_images+'/small_throbber.gif" alt="AJAX Throbber" />';
    g_BMLTPlugin_AjaxRequest = BMLTPlugin_AjaxRequest ( url, BMLTPlugin_TestRootUriCallback, 'get' );
};

/****************************************************************************************//**
*   \brief Uses AJAX to test the given root server URI.                                     *
********************************************************************************************/
function BMLTPlugin_TestRootUriCallback(in_success  ///< The text in this is either 1 or 0
                                        )
{
    var option_index = BMLTPlugin_GetSelectedOptionIndex();

    var indicator = document.getElementById ( 'BMLTPlugin_option_sheet_indicator_'+option_index );
    var version = document.getElementById ('BMLTPlugin_option_sheet_version_indicator_'+option_index );
    
    if ( !in_success.responseText || (parseInt(in_success.responseText) == 0) )
        {
        indicator.className = 'BMLTPlugin_option_sheet_BAD';
        version.innerHTML = c_g_BMLTPlugin_test_server_failure;
        }
    else
        {
        indicator.className = 'BMLTPlugin_option_sheet_OK';
        version.innerHTML = c_g_BMLTPlugin_test_server_success+in_success.responseText;
        };
};
    
/****************************************************************************************//**
*   \brief Load the map and set it up.                                                      *
********************************************************************************************/
function BMLTPlugin_admin_load_map ( )
{
    var myOptions = null;

    var option_index = BMLTPlugin_GetSelectedOptionIndex();
    var longitude = parseFloat ( c_g_BMLTPlugin_coords[option_index-1].lng );
    var latitude = parseFloat ( c_g_BMLTPlugin_coords[option_index-1].lat );
    var zoom = parseInt ( c_g_BMLTPlugin_coords[option_index-1].zoom );

    if ( !g_BMLTPlugin_admin_main_map )
        {
        myOptions = { 'zoom': zoom, 'scrollwheel': false, 'center': new google.maps.LatLng ( latitude, longitude ), 'mapTypeId': google.maps.MapTypeId.ROADMAP,
            'mapTypeControl': true,
            'mapTypeControlOptions': { 'style': google.maps.MapTypeControlStyle.DROPDOWN_MENU },
            'zoomControl': true,
            'zoomControlOptions': { 'style': google.maps.ZoomControlStyle.LARGE }
            };
    

        g_BMLTPlugin_admin_main_map = new google.maps.Map(document.getElementById("BMLTPlugin_Map_Div"), myOptions);
        google.maps.event.addListener ( g_BMLTPlugin_admin_main_map, "click", function (in_event) { g_BMLTPlugin_admin_marker.setPosition(in_event.latLng); BMLTPlugin_admin_MovedMarker(); } );
        google.maps.event.addListener ( g_BMLTPlugin_admin_main_map, "zoom_changed", function () {c_g_BMLTPlugin_coords[BMLTPlugin_GetSelectedOptionIndex()-1].zoom = g_BMLTPlugin_admin_main_map.getZoom();if(!g_BMLTPlugin_hold_the_pickles){BMLTPlugin_DirtifyOptionSheet();}else{g_BMLTPlugin_hold_the_pickles=false;};} );
        }
    else
        {
        g_BMLTPlugin_hold_the_pickles = true;
        g_BMLTPlugin_admin_main_map.panTo ( new google.maps.LatLng ( latitude, longitude ) );
        g_BMLTPlugin_admin_main_map.setZoom ( zoom );
        };
    
    
    BMLTPlugin_admin_CreateMarker ( );
};

/************************************************************************************//**
*   \brief Create a generic marker.                                                     *
****************************************************************************************/
function BMLTPlugin_admin_CreateMarker ( )
{
    /// These describe the "You are here" icon.
    var center_icon_image = new google.maps.MarkerImage ( c_g_BMLTPlugin_admin_google_map_images+'/NACenterMarker.png', new google.maps.Size(21, 36), new google.maps.Point(0,0), new google.maps.Point(11, 36) );
    var center_icon_shadow = new google.maps.MarkerImage( c_g_BMLTPlugin_admin_google_map_images+'/NACenterMarkerS.png', new google.maps.Size(43, 36), new google.maps.Point(0,0), new google.maps.Point(11, 36) );
    var center_icon_shape = { coord: [16,0,18,1,19,2,19,3,20,4,20,5,20,6,20,7,20,8,20,9,20,10,20,11,19,12,17,13,16,14,16,15,15,16,15,17,14,18,14,19,13,20,13,21,13,22,13,23,12,24,12,25,12,26,12,27,11,28,11,29,11,30,11,31,11,32,11,33,11,34,11,35,10,35,10,34,9,33,9,32,9,31,9,30,9,29,9,28,8,27,8,26,8,25,8,24,8,23,7,22,7,21,7,20,6,19,6,18,5,17,5,16,4,15,4,14,3,13,1,12,0,11,0,10,0,9,0,8,0,7,0,6,0,5,0,4,1,3,1,2,3,1,4,0,16,0], type: 'poly' };

    if ( g_BMLTPlugin_admin_marker )
        {
        g_BMLTPlugin_admin_marker.setMap( null );
        g_BMLTPlugin_admin_marker = null;
        }
    
    var option_index = BMLTPlugin_GetSelectedOptionIndex();
    var longitude = parseFloat ( c_g_BMLTPlugin_coords[option_index-1].lng );
    var latitude = parseFloat ( c_g_BMLTPlugin_coords[option_index-1].lat );

    g_BMLTPlugin_admin_marker = new google.maps.Marker ( { 'position':      new google.maps.LatLng ( latitude, longitude ),
                                                            'map':          g_BMLTPlugin_admin_main_map,
                                                            'shadow':       center_icon_shadow,
                                                            'icon':         center_icon_image,
                                                            'shape':        center_icon_shape,
                                                            'draggable':    true
                                                            } );
    if ( g_BMLTPlugin_admin_marker )
        {
        google.maps.event.addListener ( g_BMLTPlugin_admin_marker, "dragend", BMLTPlugin_admin_MovedMarker );
        };
};

/************************************************************************************//**
*   \brief Create a generic marker.                                                     *
*                                                                                       *
*   \returns a marker object.                                                           *
****************************************************************************************/
function BMLTPlugin_admin_MovedMarker ( )
{
    var option_index = BMLTPlugin_GetSelectedOptionIndex();
    BMLTPlugin_DirtifyOptionSheet();
    c_g_BMLTPlugin_coords[option_index-1].lat = g_BMLTPlugin_admin_marker.getPosition().lat();
    c_g_BMLTPlugin_coords[option_index-1].lng = g_BMLTPlugin_admin_marker.getPosition().lng();
    c_g_BMLTPlugin_coords[option_index-1].zoom = g_BMLTPlugin_admin_main_map.getZoom();
    g_BMLTPlugin_admin_main_map.panTo ( g_BMLTPlugin_admin_marker.getPosition() );
};