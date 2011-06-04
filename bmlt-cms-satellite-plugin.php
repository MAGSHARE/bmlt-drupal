<?php
/****************************************************************************************//**
*   \file   bmlt-cms-satellite-plugin.php                                                   *
*                                                                                           *
*   \brief  This is a generic CMS plugin class for a BMLT satellite client.                 *
*   \version 1.0.5                                                                          *
*                                                                                           *
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

// define ( '_DEBUG_MODE_', 1 ); //Uncomment for easier JavaScript debugging.

// Include the satellite driver class.
require_once ( dirname ( __FILE__ ).'/bmlt_satellite_controller.class.php' );

/***********************************************************************/
/** \brief	This is an open-source JSON encoder that allows us to support
	older versions of PHP (before the <a href="http://us3.php.net/json_encode">json_encode()</a> function
	was implemented). It uses json_encode() if that function is available.
	
	This is from <a href="http://www.bin-co.com/php/scripts/array2json/">Bin-Co.com</a>.
	
	This crap needs to be included to be aboveboard and legal. You can still re-use the code, but
	you need to make sure that the comments below this are included:
	

	Copyright (c) 2004-2007, Binny V Abraham

	All rights reserved.
	
	Redistribution and use in source and binary forms, with or without modification, are permitted provided
	that the following conditions are met:
	
	*	Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
	*	Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer
		in the documentation and/or other materials provided with the distribution.
	
	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING,
	BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
	IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
	PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
	OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
	EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
function array2json (
					$arr	///< An associative string, to be encoded as JSON.
					)
	{
	if(function_exists('json_encode'))
		{
		return json_encode($arr); //Lastest versions of PHP already has this functionality.
		}
	
	$parts = array();
	$is_list = false;

	//Find out if the given array is a numerical array
	$keys = array_keys($arr);
	$max_length = count($arr)-1;
	if(($keys[0] == 0) and ($keys[$max_length] == $max_length))
		{//See if the first key is 0 and last key is length - 1
		$is_list = true;
		for ($i=0; $i<count($keys); $i++)
			{ //See if each key correspondes to its position
			if($i != $keys[$i])
				{ //A key fails at position check.
				$is_list = false; //It is an associative array.
				break;
				}
			}
		}

	foreach($arr as $key=>$value)
		{
		if(is_array($value))
			{ //Custom handling for arrays
			if($is_list)
				{
				$parts[] = array2json($value); /* :RECURSION: */
				}
			else
				{
				$parts[] = '"' . $key . '":' . array2json($value); /* :RECURSION: */
				}
				
			}
		else
			{
			$str = '';
			if ( !$is_list )
				{
				$str = '"' . $key . '":';
				}

			//Custom handling for multiple data types
			if ( is_numeric($value) )
				{
				$str .= $value; //Numbers
				}
			elseif ( $value === false )
				{
				$str .= 'false'; //The booleans
				}
			elseif ( $value === true )
				{
				$str .= 'true';
				}
			elseif ( isset ($value) && $value )
				{
				$str .= '"' . addslashes($value) . '"'; //All other things
				}
			// :TODO: Is there any more datatype we should be in the lookout for? (Object?)

			$parts[] = $str;
			}
		}
	
	$json = implode ( ',', $parts );
	
	if( $is_list )
		{
		return '[' . $json . ']'; //Return numerical JSON
		}
	else
		{
		return '{' . $json . '}'; //Return associative JSON
		}
	}

/****************************************************************************************//**
*   \class BMLTPlugin                                                                       *
*                                                                                           *
*   \brief This is the class that implements and encapsulates the plugin functionality.     *
*   A single instance of this is created, and manages the plugin.                           *
*                                                                                           *
*   This plugin registers errors by echoing HTML comments, so look at the source code of    *
*   the page if things aren't working right.                                                *
********************************************************************************************/
class BMLTPlugin
{
    /************************************************************************************//**
    *                           STATIC DATA MEMBERS (SINGLETON)                             *
    ****************************************************************************************/
    
    /// This is a SINGLETON pattern. There can only be one...
    static  $g_s_there_can_only_be_one = null;                              ///< This is a static variable that holds the single instance.
    
    /************************************************************************************//**
    *                           STATIC DATA MEMBERS (DEFAULTS)                              *
    *   In Version 2, these are all ignored:                                                *
    *       $default_bmlt_fullscreen                                                        *
    *       $default_support_old_browsers                                                   *
    *       $default_sb_array                                                               *
    ****************************************************************************************/
    
    static  $adminOptionsName = "BMLTAdminOptions";                         ///< The name, in the database, for the version 1 options for this plugin.
    static  $admin2OptionsName = "BMLT2AdminOptions";                       ///< These options are for version 2.
    
    // These are the old settings that we still care about.
    static  $default_rootserver = '';                                       ///< This is the default root BMLT server URI.
    static  $default_map_center_latitude = 29.764377375163125;              ///< This is the default basic search map center latitude
    static  $default_map_center_longitude = -95.4931640625;                 ///< This is the default basic search map center longitude
    static  $default_map_zoom = 8;                                          ///< This is the default basic search map zoom level
    static  $default_new_search = '';                                       ///< If this is set to something, then a new search uses the exact URI.
    static  $default_gkey = '';                                             ///< This is only necessary for older versions.
    static  $default_push_down_more_details = '1';                          ///< If this is set to 1, then "More Details" and "Contact" windows will "push down" the content, instead of floating over it.
    static  $default_additional_css = '';                                   ///< This is additional CSS that is inserted inline into the <head> section.
    static  $default_initial_view = '';                                     ///< The initial view for old-style BMLT. It can be 'map', 'text', 'advanced', 'advanced map', 'advanced text' or ''.
    static  $default_theme = 'default';                                     ///< This is the default for the "style theme" for the plugin. Different settings can have different themes.
    static  $default_language = 'en';                                       ///< The default language is English, but the root server can override.
    static  $default_language_string = 'English';                           ///< The default language is English, and the name is spelled out, here.
    static  $default_distance_units = 'mi';                                 ///< The default distance units are miles.
    static  $default_grace_period = 15;                                     ///< The default grace period for the mobile search (in minutes).
    static  $default_time_offset = 0;                                       ///< The default time offset from the main server (in hours).
    
    /************************************************************************************//**
    *                           STATIC DATA MEMBERS (LOCALIZABLE)                           *
    ****************************************************************************************/

    /// These are all for the admin pages.
    static  $local_options_title = 'Basic Meeting List Toolbox Options';    ///< This is the title that is displayed over the options.
    static  $local_menu_string = 'BMLT Options';                            ///< The name of the menu item.
    static  $local_options_prefix = 'Select Setting ';                      ///< The string displayed before each number in the options popup.
    static  $local_options_add_new = 'Add A new Setting';                   ///< The string displayed in the "Add New Option" button.
    static  $local_options_save = 'Save Changes';                           ///< The string displayed in the "Save Changes" button.
    static  $local_options_delete_option = 'Delete This Setting';           ///< The string displayed in the "Delete Option" button.
    static  $local_options_delete_failure = 'The setting deletion failed.'; ///< The string displayed upon unsuccessful deletion of an option page.
    static  $local_options_create_failure = 'The setting creation failed.'; ///< The string displayed upon unsuccessful creation of an option page.
    static  $local_options_delete_option_confirm = 'Are you sure that you want to delete this setting?';    ///< The string displayed in the "Are you sure?" confirm.
    static  $local_options_delete_success = 'The setting was deleted successfully.';                        ///< The string displayed upon successful deletion of an option page.
    static  $local_options_create_success = 'The setting was created successfully.';                        ///< The string displayed upon successful creation of an option page.
    static  $local_options_save_success = 'The settings were updated successfully.';                        ///< The string displayed upon successful update of an option page.
    static  $local_options_save_failure = 'The settings were not updated.';                                 ///< The string displayed upon unsuccessful update of an option page.
    static  $local_options_url_bad = 'This root server URL will not work for this plugin.';                 ///< The string displayed if a root server URI fails to point to a valid root server.
    static  $local_options_access_failure = 'You are not allowed to perform this operation.';               ///< This is displayed if a user attempts a no-no.
    static  $local_options_unsaved_message = 'You have unsaved changes. Are you sure you want to leave without saving them?';   ///< This is displayed if a user attempts to leave a page without saving the options.
    static  $local_options_settings_id_prompt = 'The ID for this Setting is ';                              ///< This is so that users can see the ID for the setting.
    
    /// These are all for the admin page option sheets.
    static  $local_options_name_label = 'Setting Name:';                    ///< The Label for the setting name item.
    static  $local_options_rootserver_label = 'Root Server:';               ///< The Label for the root server item.
    static  $local_options_new_search_label = 'New Search URL:';            ///< The Label for the new search item.
    static  $local_options_gkey_label = 'Google Maps API Key:';             ///< The Label for the Google Maps API Key item.
    static  $local_options_no_name_string = 'Enter Setting Name';           ///< The Value to use for a name field for a setting with no name.
    static  $local_options_no_root_server_string = 'Enter a Root Server URL';                               ///< The Value to use for a root with no URL.
    static  $local_options_no_new_search_string = 'Enter a New Search URL'; ///< The Value to use for a new search with no URL.
    static  $local_options_no_gkey_string = 'Enter a New API Key';          ///< The Value to use for a new search with no URL.
    static  $local_options_test_server = 'Test';                            ///< This is the title for the "test server" button.
    static  $local_options_fetch_server_langs = 'Fetch Server Languages';   ///< This is the title for the "Fetch Server Languages" button.
    static  $local_options_fetch_server_langs_tooltip = 'If you press this button, the server will be queried for its available and default languages.';    ///< This is the tooltip for the "Fetch Server Languages" button.
    static  $local_options_test_server_success = 'Version ';                ///< This is a prefix for the version, on success.
    static  $local_options_test_server_failure = 'This Root Server URL is not Valid';                       ///< This is a prefix for the version, on failure.
    static  $local_options_test_server_tooltip = 'This tests the root server, to see if it is OK.';         ///< This is the tooltip text for the "test server" button.
    static  $local_options_map_label = 'Select a Center Point and Zoom Level for Map Displays';             ///< The Label for the map.
    static  $local_options_gkey_caveat = 'These are only necessary for old-style BMLT implementations';     ///< This lets people know that this is not necessary for newer installs.
    static  $local_options_mobile_legend = 'These are for the fast mobile lookup';                          ///< This indicates that the enclosed settings are for the fast mobile lookup.
    static  $local_options_mobile_grace_period_label = 'Grace Period:';     ///< When you do a "later today" search, you get a "Grace Period."
    static  $local_options_mobile_time_offset_label = 'Time Offset:';       ///< This may have an offset (time zone difference) from the main server.
    static  $local_options_initial_view = array (                           ///< The list of choices for presentation in the popup.
                                                '' => 'Root Server Decides', 'map' => 'Map', 'text' => 'Text', 'advanced' => 'Advanced (Server Decides)', 'advanced_map' => 'Advanced Map', 'advanced_text' => 'Advanced Text'
                                                );
    static  $local_options_initial_view_prompt = 'Initial Search Type:';    ///< The label for the initial view popup.
    static  $local_options_theme_prompt = 'Select a Color Theme:';          ///< The label for the theme selection popup.
    static  $local_options_push_down_checkbox_label = '"More Details" Windows "push down" the main list or map, as opposed to popping up over them.';       ///< The label for the "more details" checkbox.
    static  $local_options_more_styles_label = 'Add CSS Styles to the Plugin:';                             ///< The label for the Additional CSS textarea.
    static  $local_single_meeting_tooltip = 'Follow This Link for Details About This Meeting.'; ///< The tooltip shown for a single meeting.
    static  $local_gm_link_tooltip = 'Follow This Link to be Taken to A Google Maps Location for This Meeting.';    ///< The tooltip shown for the Google Maps link.
    static  $local_not_enough_for_old_style = 'In order to display the "classic" BMLT window, you need to have both a root server and a Google Maps API key in the corresponding setting.'; ///< Displayed if there is no GMAP API key.
    static  $local_options_language_prompt = 'Language:';                   ///< This is for the language select.
    static  $local_options_distance_prompt = 'Distance Units:';             ///< This is for the distance units select.
    static  $local_options_distance_disclaimer = 'This will not affect all of the displays.';               ///< This tells the admin that only some stuff will be affected.
    static  $local_options_grace_period_disclaimer = 'Minutes Elapsed Before A Meeting is Considered "Past."';      ///< This explains what the grace period means.
    static  $local_options_time_offset_disclaimer = 'Hours of Difference From the Main Server.';            ///< This explains what the time offset means.
    static  $local_options_miles = 'Miles';                                 ///< The string for miles.
    static  $local_options_kilometers = 'Kilometers';                       ///< The string for kilometers.
    
    /// These are for the actual search displays
    static  $local_select_search = 'Select a Quick Search';                 ///< Used for the "filler" in the quick search popup.
    static  $local_clear_search = 'Clear Search Results';                   ///< Used for the "Clear" item in the quick search popup.
    static  $local_menu_new_search_text = 'New Search';                     ///< For the new search menu in the old-style BMLT search.
    
    /// A simple message for most <noscript> elements. We have a different one for the older interactive search (below).
    static  $local_noscript = 'This will not work, because you do not have JavaScript active.';             ///< The string displayed in a <noscript> element.
                                    
    /************************************************************************************//**
    *                      STATIC DATA MEMBERS (SPECIAL LOCALIZABLE)                        *
    ****************************************************************************************/

    /// This is the only localizable string that is not processed. This is because it contains HTML. However, it is also a "hidden" string that is only displayed when the browser does not support JS.
    static  $local_no_js_warning = '<noscript class="no_js">This Meeting Search will not work because your browser does not support JavaScript. However, you can use the <a rel="external nofollow" href="###ROOT_SERVER###">main server</a> to do the search.</noscript>'; ///< This is the noscript presented for the old-style meeting search. It directs the user to the root server, which will support non-JS browsers.
     
   
    /************************************************************************************//**
    *                               STATIC DATA MEMBERS (MISC)                              *
    ****************************************************************************************/
    
    static  $local_options_success_time = 2000;                             ///< The number of milliseconds a success message is displayed.
    static  $local_options_failure_time = 5000;                             ///< The number of milliseconds a failure message is displayed.
                                    
    /************************************************************************************//**
    *                       STATIC DATA MEMBERS (MOBILE LOCALIZABLE)                        *
    ****************************************************************************************/

    /// The units for distance.
    static  $local_mobile_kilometers = 'Kilometers';
    static  $local_mobile_miles = 'Miles';
    static  $local_mobile_distance = 'Distance';  ///< Distance (the string)

    /// The page titles.
    static  $local_mobile_results_page_title = 'Quick Meeting Search Results';
    static  $local_mobile_results_form_title = 'Find Nearby Meetings Quickly';

    /// The fast GPS lookup links.
    static  $local_GPS_banner = 'Select A Fast Meeting Lookup';
    static  $local_GPS_banner_subtext = 'Bookmark these links for even faster searches in the future.';
    static  $local_search_all = 'Search for all meetings near my present location.';
    static  $local_search_today = 'Later Today';
    static  $local_search_tomorrow = 'Tomorrow';

    /// The search for an address form.
    static  $local_list_check = 'If you are experiencing difficulty with the interactive map, or wish to have the results returned as a list, check this box and enter an address.';
    static  $local_search_address_single = 'Search for Meetings Near An Address';

    /// Used instead of "near my present location."
    static  $local_search_all_address = 'Search for all meetings near this address.';
    static  $local_search_submit_button = 'Search For Meetings';

    /// This is what is entered into the text box.
    static  $local_enter_an_address = 'Enter An Address';

    /// Error messages.
    static  $local_mobile_fail_no_meetings = 'No Meetings Found!';
    static  $local_server_fail = 'The search failed because the server encountered an error!';
    static  $local_cant_find_address = 'Cannot Determine the Location From the Address Information!';
    static  $local_cannot_determine_location = 'Cannot Determine Your Current Location!';
    static  $local_enter_address_alert = 'Please enter an address!';

    /// The text for the "Map to Meeting" links
    static  $local_map_link = 'Map to Meeting';

    /// Only used for WML pages
    static  $local_next_card = 'Next Meeting >>';
    static  $local_prev_card = '<< Previous Meeting';
    
    /// Used for the info and list windows.
    static  $local_formats = 'Formats';
    static  $local_noon = 'Noon';
    static  $local_midnight = 'Midnight';

    /// This array has the weekdays, spelled out. Since weekdays start at 1 (Sunday), we consider 0 to be an error.
    static	$local_weekdays = array ( 'ERROR', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );

    /************************************************************************************//**
    *                                  DYNAMIC DATA MEMBERS                                 *
    ****************************************************************************************/
    
    var $my_driver = null;              ///< This will contain an instance of the BMLT satellite driver class.
    var $my_params = null;              ///< This will contain the $this->my_http_vars and $_POST query variables.
    var $my_http_vars = null;           ///< This will hold all of the query arguments.
    
    /************************************************************************************//**
    *                                    FUNCTIONS/METHODS                                  *
    ****************************************************************************************/
    
    /************************************************************************************//**
    *   \brief Get the instance                                                             *
    *                                                                                       *
    *   \return An instance  of BMLTPlugin                                                  *
    ****************************************************************************************/
    static function get_plugin_object ()
        {
        return self::$g_s_there_can_only_be_one;
        }
    
    /****************************************************************************************//**
    *   \brief Checks the UA of the caller, to see if it should return XHTML Strict or WML.     *
    *                                                                                           *
    *   NOTE: This is very, very basic. It is not meant to be a studly check, like WURFL.       *
    *                                                                                           *
    *   \returns A string. The supported type ('xhtml', 'xhtml_mp' or 'wml')                    *
    ********************************************************************************************/
    static function mobile_sniff_ua (   $in_http_vars   ///< The query variables.
                                    )
    {
        if ( isset ( $in_http_vars['WML'] ) && (intval ( $in_http_vars['WML'] ) == 1) )
            {
            $language = 'wml';
            }
        elseif ( isset ( $in_http_vars['WML'] ) && (intval ( $in_http_vars['WML'] ) == 2) )
            {
            $language = 'xhtml_mp';
            }
        else
            {
            if (!isset($_SERVER['HTTP_ACCEPT']))
                {
                return false;
                }
        
            $http_accept = explode (',', $_SERVER['HTTP_ACCEPT']);
        
            $accept = array();
        
            foreach ($http_accept as $type)
                {
                $type = strtolower(trim(preg_replace('/\;.*$/', '', preg_replace ('/\s+/', '', $type))));
        
                $accept[$type] = true;
                }
        
            $language = 'xhtml';
        
            if (isset($accept['text/vnd.wap.wml']))
                {
                $language = 'wml';
        
                if (isset($accept['application/xhtml+xml']) || isset($accept['application/vnd.wap.xhtml+xml']))
                    {
                    $language = 'xhtml_mp';
                    }
                }
            else
                {
                if (    preg_match ( '/ipod/i', $_SERVER['HTTP_USER_AGENT'] )
                    ||  preg_match ( '/ipad/i', $_SERVER['HTTP_USER_AGENT'] )
                    ||  preg_match ( '/iphone/i', $_SERVER['HTTP_USER_AGENT'] )
                    ||  preg_match ( '/android/i', $_SERVER['HTTP_USER_AGENT'] )
                    ||  preg_match ( '/blackberry/i', $_SERVER['HTTP_USER_AGENT'] )
                    ||  preg_match ( "/opera\s+mini/i", $_SERVER['HTTP_USER_AGENT'] )
                    ||  isset ( $in_http_vars['simulate_smartphone'] )
                    )
                    {
                    $language = 'smartphone';
                    }
                }
            }
        return $language;
    }
        
    /************************************************************************************//**
    *                           ACCESSORS AND INTERNAL FUNCTIONS                            *
    ****************************************************************************************/
    
    /************************************************************************************//**
    *   \brief Accessor: This gets the driver object.                                       *
    *                                                                                       *
    *   \returns a reference to the bmlt_satellite_controller driver object                 *
    ****************************************************************************************/
    function &get_my_driver ()
        {
        return $this->my_driver;
        }
    
    /************************************************************************************//**
    *   \brief Loads a parameter list.                                                      *
    *                                                                                       *
    *   \returns a string, containing the joined parameters.                                *
    ****************************************************************************************/
    static function get_params ( $in_array )
        {
        $my_params = '';

        foreach ( $in_array as $key => $value )
            {
            if ( ($key != 'lang_enum') && isset ( $in_array['direct_simple'] ) || (!isset ( $in_array['direct_simple'] ) && $key != 'switcher') )    // We don't propagate switcher or the language.
                {
                if ( isset ( $value ) && is_array ( $value ) && count ( $value ) )
                    {
                    foreach ( $value as $val )
                        {
                        if ( isset ( $val ) &&  is_array ( $val ) && count ( $val ) )
                            {
                            $val = implode ( ',', $val );
                            }
                        elseif ( !isset ( $val ) )
                            {
                            $val = '';
                            }
                        $my_params .= '&'.urlencode ( $key ) ."[]=". urlencode ( $val );
                        }
                    $key = null;
                    }
                
                if ( $key )
                    {
                    $my_params .= '&'.urlencode ( $key );
                    
                    if ( $value )
                        {
                        $my_params .= "=". urlencode ( $value );
                        }
                    }
                }
            }
        return $my_params;
        }
    
    /************************************************************************************//**
    *   \brief Loads the parameter list.                                                    *
    ****************************************************************************************/
    function load_params ( )
        {
        $this->my_params = self::get_params ( $this->my_http_vars );
        }

    /************************************************************************************//**
    *   \brief This will parse the given text, to see if it contains the submitted code.    *
    *                                                                                       *
    *   The code can be contained in EITHER an HTML comment (<!--CODE-->), OR a double-[[]] *
    *   notation.                                                                           *
    *                                                                                       *
    *   \returns Boolean true if the code is found (1 or more instances), OR an associative *
    *   array of data that is associated with the code (anything within parentheses). Null  *
    *   is returned if there is no shortcode detected.                                      *
    ****************************************************************************************/
    static function get_shortcode ( $in_text_to_parse,  ///< The text to search for shortcodes
                                    $in_code            ///< The code that w're looking for.
                                    )
        {
        $ret = null;
        
        $code_regex_html = "\<\!\-\-\s?".preg_quote ( strtolower ( trim ( $in_code ) ) )."\s?(\(.*?\))?\s?\-\-\>";
        $code_regex_brackets = "\[\[\s?".preg_quote ( strtolower ( trim ( $in_code ) ) )."\s?(\(.*?\))?\s?\]\]";
        
        $matches = array();
        
        if ( preg_match ( '#'.$code_regex_html.'#i', $in_text_to_parse, $matches ) || preg_match ( '#'.$code_regex_brackets.'#i', $in_text_to_parse, $matches ) )
            {
            if ( !isset ( $matches[1] ) || !($ret = trim ( $matches[1], '()' )) ) // See if we have any parameters.
                {
                $ret = true;
                }
            }
        
        return $ret;
        }

    /************************************************************************************//**
    *   \brief This will parse the given text, to see if it contains the submitted code.    *
    *                                                                                       *
    *   The code can be contained in EITHER an HTML comment (<!--CODE-->), OR a double-[[]] *
    *   notation.                                                                           *
    *                                                                                       *
    *   \returns A string, consisting of the new text.                                      *
    ****************************************************************************************/
    static function replace_shortcode ( $in_text_to_parse,      ///< The text to search for shortcodes
                                        $in_code,               ///< The code that w're looking for.
                                        $in_replacement_text    ///< The text we'll be replacing the shortcode with.
                                        )
        {
        $code_regex_html = "#(\<p[^\>]*?\>)?\<\!\-\-\s?".preg_quote ( strtolower ( trim ( $in_code ) ) )."\s?(\(.*?\))?\s?\-\-\>(\<\/p>)?#i";
        $code_regex_brackets = "#(\<p[^\>]*?\>)?\[\[\s?".preg_quote ( strtolower ( trim ( $in_code ) ) )."\s?(\(.*?\))?\s?\]\](\<\/p>)?#i";

        $ret = preg_replace ( $code_regex_html, $in_replacement_text, $in_text_to_parse, 1 );
        $ret = preg_replace ( $code_regex_brackets, $in_replacement_text, $ret, 1 );
        
        return $ret;
        }
    
    /************************************************************************************//**
    *                               OPTIONS MANAGEMENT                                      *
    *****************************************************************************************
    *   This takes some 'splainin'.                                                         *
    *                                                                                       *
    *   The admin2 options track how many servers we're tracking, and allow the admin to    *
    *   increment by 1. The first options don't have a number. "Numbered" options begin at  *
    *   2. You are allowed to save new options at 1 past the current number of options. You *
    *   delete options by decrementing the number in the admin2 options (the index). If you *
    *   re-increment the options, you will see the old values. It is possible to reset to   *
    *   default, and you do that by specifying an option number less than 0 (-1).           *
    *                                                                                       *
    *   The reason for this funky, complex game, is so we can have multiple options, and we *
    *   don't ignore old options from previous versions.                                    *
    *                                                                                       *
    *   I considered setting up an abstracted, object-based system for managing these, but  *
    *   it's complex enough without the added overhead, and, besides, that would give a lot *
    *   more room for bugs. It's kinda hairy already, and the complexity is not great       *
    *   enough to justify designing a whole object subsystem for it.                        *
    ****************************************************************************************/
        
    /************************************************************************************//**
    *   \brief This gets the default admin options from the object (not the DB).            *
    *                                                                                       *
    *   \returns an associative array, with the default option settings.                    *
    ****************************************************************************************/
    protected function geDefaultBMLTOptions ()
        {
        // These are the defaults. If the saved option has a different value, it replaces the ones in here.
        return array (  'root_server' => self::$default_rootserver,
                        'map_center_latitude' => self::$default_map_center_latitude,
                        'map_center_longitude' => self::$default_map_center_longitude,
                        'map_zoom' => self::$default_map_zoom,
                        'bmlt_new_search_url' => self::$default_new_search,
                        'gmaps_api_key' => self::$default_gkey,
                        'bmlt_initial_view' => self::$default_initial_view,
                        'push_down_more_details' => self::$default_push_down_more_details,
                        'additional_css' => self::$default_additional_css,
                        'id' => strval ( time() + intval(rand(0, 999))),   // This gives the option a unique slug
                        'setting_name' => '',
                        'theme' => self::$default_theme,
                        'lang_enum' => self::$default_language,
                        'lang_name' => self::$default_language_string,
                        'distance_units' => self::$default_distance_units,
                        'grace_period' => self::$default_grace_period,
                        'time_offset' => self::$default_time_offset
                        );
        }
    
    /************************************************************************************//**
    *   \brief This gets the admin options from the database.                               *
    *                                                                                       *
    *   \returns an associative array, with the option settings.                            *
    ****************************************************************************************/
    function getBMLTOptions ( $in_option_number = null  /**<    It is possible to store multiple options.
                                                                If there is a number here (>1), that will be used.
                                                                If <0, a new option will be returned (not saved).
                                                        */
                            )
        {
        $BMLTOptions = $this->geDefaultBMLTOptions(); // Start off with the defaults.
        
        // Make sure we aren't resetting to default.
        if ( ($in_option_number == null) || (intval ( $in_option_number ) > 0) )
            {
            $option_number = null;
            // If they want a certain option number, then it needs to be greater than 1, and within the number we have assigned.
            if ( (intval ( $in_option_number ) > 1) && (intval ( $in_option_number ) <= $this->get_num_options ( ) ) )
                {
                $option_number = '_'.intval( $in_option_number );
                }
        
            // These are the standard options.
            $old_BMLTOptions = $this->cms_get_option ( self::$adminOptionsName.$option_number );
            
            if ( is_array ( $old_BMLTOptions ) && count ( $old_BMLTOptions ) )
                {
                foreach ( $old_BMLTOptions as $key => $value )
                    {
                    if ( isset ( $BMLTOptions[$key] ) ) // We deliberately ignore old settings that no longer apply.
                        {
                        $BMLTOptions[$key] = $value;
                        }
                    }
                }
        
            // Strip off the trailing slash.
            $BMLTOptions['root_server'] = preg_replace ( "#\/$#", "", trim($BMLTOptions['root_server']), 1 );
            }
        else
            {
            $in_option_number = $this->get_num_options() + 1;
            }
        
        $this->setBMLTOptions ( $BMLTOptions, $in_option_number );
        
        return $BMLTOptions;
        }
    
    /************************************************************************************//**
    *   \brief This gets the admin options from the database, but by using the option id.   *
    *                                                                                       *
    *   \returns an associative array, with the option settings.                            *
    ****************************************************************************************/
    function getBMLTOptions_by_id ( $in_option_id,              ///< The option ID. It cannot be optional.
                                    &$out_option_number = null  ///< This can be optional. A reference to an integer that will be given the option number.
                                    )
        {
        $BMLTOptions = null;
        
        if ( isset ( $out_option_number ) )
            {
            $out_option_number = 0;
            }
        
        if ( !$in_option_id )
            {
            $BMLTOptions = $this->getBMLTOptions ( 1 );
            
            if ( isset ( $out_option_number ) )
                {
                $out_option_number = 1;
                }
            }
        else
            {
            $count = $this->get_num_options ( );
            
            // We sort through the available options, looking for the ID.
            for ( $i = 1; $i <= $count; $i++ )
                {
                $option_number = '';
                
                if ( $i > 1 )   // We do this, for compatibility with older options.
                    {
                    $option_number = "_$i";
                    }
                
                $name = self::$adminOptionsName.$option_number;
                $temp_BMLTOptions = $this->cms_get_option ( $name );
                
                if ( is_array ( $temp_BMLTOptions ) && count ( $temp_BMLTOptions ) )
                    {
                    if ( $temp_BMLTOptions['id'] == $in_option_id )
                        {
                        $BMLTOptions = $temp_BMLTOptions;
                        // If they want to know the ID, we supply it here.
                        if ( isset ( $out_option_number ) )
                            {
                            $out_option_number = $i;
                            }
                        break;
                        }
                    }
                else
                    {
                    echo "<!-- BMLTPlugin ERROR (getBMLTOptions_by_id)! No options found for $name! -->";
                    }
                }
            }
        
        return $BMLTOptions;
        }
    
    /************************************************************************************//**
    *   \brief This updates the database with the given options.                            *
    *                                                                                       *
    *   \returns a boolean. true if success.                                                *
    ****************************************************************************************/
    function setBMLTOptions (   $in_options,            ///< An array. The options to be stored. If no number is supplied in the next parameter, the ID is used.
                                $in_option_number = 1   ///< It is possible to store multiple options. If there is a number here, that will be used.
                            )
        {
        $ret = false;
        
        if ( ($in_option_number == null) || (intval($in_option_number) < 1) || (intval($in_option_number) > ($this->get_num_options ( ) + 1)) )
            {
            $in_option_number = 0;
            $this->getBMLTOptions_by_id ( $in_options['id'], $in_option_number );
            }
        
        if ( intval ( $in_option_number ) > 0 )
            {
            $option_number = null;
            // If they want a certain option number, then it needs to be greater than 1, and within the number we have assigned (We can also increase by 1).
            if ( (intval ( $in_option_number ) > 1) && (intval ( $in_option_number ) <= ($this->get_num_options ( ) + 1)) )
                {
                $option_number = '_'.intval( $in_option_number );
                }
            $in_option_number = (intval ( $in_option_number ) > 1) ? intval ( $in_option_number ) : 1;
            
            $name = self::$adminOptionsName.$option_number;
            
            // If this is a new option, then we also update the admin 2 options, incrementing the number of servers.
            if ( intval ( $in_option_number ) == ($this->get_num_options ( ) + 1) )
                {
                $in_options['id'] = strval ( time() + intval(rand(0, 999)));   // This gives the option a unique slug
                $admin2Options = array ('num_servers' => intval( $in_option_number ));

                $this->setAdmin2Options ( $admin2Options );
                }
            
            $this->cms_set_option ( $name, $in_options );
            
            $ret = true;
            }
        else
            {
            echo "<!-- BMLTPlugin ERROR (setBMLTOptions)! The option number ($in_option_number) is out of range! -->";
            }
            
        return $ret;
        }
        
    /************************************************************************************//**

    *   \brief This gets the admin 2 options from the database.                             *
    *                                                                                       *
    *   \returns an associative array, with the option settings.                            *
    ****************************************************************************************/
    function getAdmin2Options ( )
        {
        $bmlt2_BMLTOptions = null;
        
        $bmlt2_BMLTOptions = array ('num_servers' => 1  ///< This is how many servers we start with (1)
                                    );
        // We have a special set of options for version 2.
        $old_BMLTOptions = $this->cms_get_option ( self::$admin2OptionsName );
        
        if ( is_array ( $old_BMLTOptions ) && count ( $old_BMLTOptions ) )
            {
            foreach ( $old_BMLTOptions as $key => $value )
                {
                $bmlt2_BMLTOptions[$key] = $value;
                }
            }
        
        $this->setAdmin2Options ( $bmlt2_BMLTOptions );
            
        return $bmlt2_BMLTOptions;
        }
    
    /************************************************************************************//**
    *   \brief This updates the database with the given options (Admin2 options).           *
    *                                                                                       *
    *   \returns a boolean. true if success.                                                *
    ****************************************************************************************/
    function setAdmin2Options ( $in_options ///< An array. The options to be stored.
                                )
        {
        $ret = false;
        
        if ( $this->cms_set_option ( self::$admin2OptionsName, $in_options ) )
            {
            $ret = true;
            }
        
        return $ret;
        }
    
    /************************************************************************************//**
    *   \brief Gets the number of active options.                                           *
    *                                                                                       *
    *   \returns an integer. The number of options.                                         *
    ****************************************************************************************/
    function get_num_options ( )
        {
        $ret = 1;
        $opts = $this->getAdmin2Options();
        if ( isset ( $opts['num_servers'] ) )
            {
            $ret = intval ( $opts['num_servers'] );
            }
        else    // If the options weren't already set, we create them now.
            {
            $opts = array ( 'num_servers' => 1 );
            $this->setAdmin2Options ( $opts );
            }
        return $ret;
        }
    
    /************************************************************************************//**
    *   \brief Makes a new set of options, set as default.                                  *
    *                                                                                       *
    *   \returns An integer. The index of the options (It will always be the number of      *
    *   initial options, plus 1. Null if failed.                                            *
    ****************************************************************************************/
    function make_new_options ( )
        {
        $opt = $this->getBMLTOptions ( -1 );
        $ret = null;
        
        // If we successfully get the options, we save them, in order to put them in place
        if ( is_array ( $opt ) && count ( $opt ) )
            {
            $ret = $this->get_num_options ( );
            }
        else
            {
            echo "<!-- BMLTPlugin ERROR (make_new_options)! Failed to create new options! -->";
            }
        
        return $ret;
        }
    
    /************************************************************************************//**
    *   \brief Deletes the options by ID.                                                   *
    *                                                                                       *
    *   \returns a boolean. true if success.                                                *
    ****************************************************************************************/
    function delete_options_by_id ( $in_option_id   ///< The ID of the option to delete.
                                    )
        {
        $ret = false;
        
        $option_num = 0;
        $this->getBMLTOptions_by_id ( $in_option_id, $option_num ); // We just want the option number.
        
        if ( $option_num > 0 )  // If it's 1, we'll let the next function register the error.
            {
            $ret = $this->delete_options ( $option_num );
            }
        
        return $ret;
        }
    
    /************************************************************************************//**
    *   \brief Deletes the indexed options.                                                 *
    *                                                                                       *
    *   This is a bit of a delicate operation, because we need to re-index all of the other *
    *   options, beyond the one being deleted.                                              *
    *                                                                                       *
    *   You cannot delete the first options (1), if they are the only ones.                 *
    *                                                                                       *
    *   \returns a boolean. true if success.                                                *
    ****************************************************************************************/
    function delete_options ( $in_option_number /**<    The index of the option to delete.
                                                        It can be 1 -> the number of available options.
                                                        For safety's sake, this cannot be optional.
                                                        We cannot delete the first (primary) option if there are no others.
                                                */
                            )
        {
        $first_num = intval ( $in_option_number );

        $ret = false;
        
        if ( $first_num )
            {
            $last_num = $this->get_num_options ( );
            
            if ( (($first_num > 1) && ($first_num <= $last_num )) || (($first_num == 1) && ($last_num > 1)) )
                {
                /*
                    OK. At this point, we know which option we'll be deleting. The way we "delete"
                    the option is to cascade all the ones after it down, and then we delete the last one.
                    If this is the last one, then there's no need for a cascade, and we simply delete it.
                */
                
                for ( $i = $first_num; $i < $last_num; $i++ )
                    {
                    $opt = $this->getBMLTOptions ( $i + 1 );
                    $this->setBMLTOptions ( $opt, $i );
                    }
                
                $option_number = "_$last_num";
                
                // Delete the selected option
                $option_name = self::$adminOptionsName.$option_number;
                
                $this->cms_delete_option ( $option_name );
                
                // This actually decrements the number of available options.
                $admin2Options = array ('num_servers' => $last_num - 1);

                $this->setAdmin2Options ( $admin2Options );
                $ret = true;
                }
            else
                {
                if ( $first_num > 1 )
                    {
                    echo "<!-- BMLTPlugin ERROR (delete_options)! Option request number out of range! It must be between 1 and $last_num -->";
                    }
                elseif ( $first_num == 1 )
                    {
                    echo "<!-- BMLTPlugin ERROR (delete_options)! You can't delete the last option! -->";
                    }
                else
                    {
                    echo "<!-- BMLTPlugin ERROR (delete_options)! -->";
                    }
                }
            }
        else
            {
            echo "<!-- BMLTPlugin ERROR (delete_options)! Option request number ($first_num) out of range! -->";
            }
        
        return $ret;
        }
    
    /************************************************************************************//**
    *                      ADMIN PAGE DISPLAY AND PROCESSING FUNCTIONS                      *
    ****************************************************************************************/

    /************************************************************************************//**
    *   \brief This does any admin actions necessary.                                       *
    ****************************************************************************************/
    function process_admin_page ( &$out_option_number   ///< If an option number needs to be selected, it is set here.
                                )
        {
        $out_option_number = 1;
        
        $timing = self::$local_options_success_time;    // Success is a shorter fade, but failure is longer.
        $ret = '<div id="BMLTPlugin_Message_bar_div" class="BMLTPlugin_Message_bar_div">';
            if ( isset ( $this->my_http_vars['BMLTPlugin_create_option'] ) )
                {
                $out_option_number = $this->make_new_options ( );
                if ( $out_option_number )
                    {
                    $new_options = $this->getBMLTOptions ( $out_option_number );
                    $def_options = $this->getBMLTOptions ( 1 );
                    
                    $new_options = $def_options;
                    unset ( $new_options['setting_name'] );
                    unset ( $new_options['id'] );
                    unset ( $new_options['theme'] );
                    $this->setBMLTOptions ( $new_options, $out_option_number );
                    
                    $ret .= '<h2 id="BMLTPlugin_Fader" class="BMLTPlugin_Message_bar_success">';
                        $ret .= $this->process_text ( self::$local_options_create_success );
                    $ret .= '</h2>';
                    }
                else
                    {
                    $timing = self::$local_options_failure_time;
                    $ret .= '<h2 id="BMLTPlugin_Fader" class="BMLTPlugin_Message_bar_fail">';
                        $ret .= $this->process_text ( self::$local_options_create_failure );
                    $ret .= '</h2>';
                    }
                }
            elseif ( isset ( $this->my_http_vars['BMLTPlugin_delete_option'] ) )
                {
                $option_index = intval ( $this->my_http_vars['BMLTPlugin_delete_option'] );
        
                if ( $this->delete_options ( $option_index ) )
                    {
                    $ret .= '<h2 id="BMLTPlugin_Fader" class="BMLTPlugin_Message_bar_success">';
                        $ret .= $this->process_text ( self::$local_options_delete_success );
                    $ret .= '</h2>';
                    }
                else
                    {
                    $timing = self::$local_options_failure_time;
                    $ret .= '<h2 id="BMLTPlugin_Fader" class="BMLTPlugin_Message_bar_fail">';
                        $ret .= $this->process_text ( self::$local_options_delete_failure );
                    $ret .= '</h2>';
                    }
                }
            else
                {
                $ret .= '<h2 id="BMLTPlugin_Fader" class="BMLTPlugin_Message_bar_fail">&nbsp;</h2>';
                }
            $ret .= '<script type="text/javascript">g_BMLTPlugin_TimeToFade = '.$timing.';BMLTPlugin_StartFader()</script>';
        $ret .= '</div>';
        return $ret;
        }
        
    /************************************************************************************//**
    *   \brief Returns the HTML for the admin page.                                         *
    *                                                                                       *
    *   \returns a string. The XHTML for the page.                                          *
    ****************************************************************************************/
    function return_admin_page ( )
        {
        $selected_option = 1;
        $process_html = $this->process_admin_page($selected_option);
        $options_coords = array();

        $html = '<div class="BMLTPlugin_option_page" id="BMLTPlugin_option_page_div">';
            $html .= '<noscript class="no_js">'.$this->process_text ( self::$local_noscript ).'</noscript>';
            $html .= '<div id="BMLTPlugin_options_container" style="display:none">';    // This is displayed using JavaScript.
                $html .= '<h1 class="BMLTPlugin_Admin_h1">'.$this->process_text ( self::$local_options_title ).'</h1>';
                $html .= $process_html;
                $html .= '<form class="BMLTPlugin_sheet_form" id="BMLTPlugin_sheet_form" action ="'.$this->get_admin_form_uri().'" method="get" onsubmit="function(){return false}">';
                    $html .= '<fieldset class="BMLTPlugin_option_fieldset" id="BMLTPlugin_option_fieldset">';
                        $html .= '<legend id="BMLTPlugin_legend" class="BMLTPlugin_legend">';
                            $count = $this->get_num_options();
                                
                            if ( $count > 1 )
                                {
                                $html .= '<select id="BMLTPlugin_legend_select" onchange="BMLTPlugin_SelectOptionSheet(this.value,'.$count.')">';
                                    for ( $i = 1; $i <= $count; $i++ )
                                        {
                                        $options = $this->getBMLTOptions ( $i );
                                        
                                        if ( is_array ( $options ) && count ( $options ) && isset ( $options['id'] ) )
                                            {
                                            $options_coords[$i] = array ( 'lat' => $options['map_center_latitude'], 'lng' => $options['map_center_longitude'], 'zoom' => $options['map_zoom'] );
                                            
                                            $html .= '<option id="BMLTPlugin_option_sel_'.$i.'" value="'.$i.'"';
                                            
                                            if ( $i == $selected_option )
                                                {
                                                $html .= ' selected="selected"';
                                                }
                                            
                                            $html .= '>';
                                                if ( isset ( $options['setting_name'] ) && $options['setting_name'] )
                                                    {
                                                    $html .= htmlspecialchars ( $options['setting_name'] );
                                                    }
                                                else
                                                    {
                                                    $html .= $this->process_text ( self::$local_options_prefix ).$i;
                                                    }
                                            $html .= '</option>';
                                            }
                                        else
                                            {
                                            echo "<!-- BMLTPlugin ERROR (admin_page)! Options not found for $i! -->";
                                            }
                                        }
                                $html .= '</select>';
                                }
                            elseif ( $count == 1 )
                                {
                                $options = $this->getBMLTOptions ( 1 );
                                $options_coords[1] = array ( 'lat' => $options['map_center_latitude'], 'lng' => $options['map_center_longitude'], 'zoom' => $options['map_zoom'] );
                                if ( isset ( $options['setting_name'] ) && $options['setting_name'] )
                                    {
                                    $html .= htmlspecialchars ( $options['setting_name'] );
                                    }
                                else
                                    {
                                    $html .= $this->process_text ( self::$local_options_prefix ).'1';
                                    }
                                }
                            else
                                {
                                echo "<!-- BMLTPlugin ERROR (admin_page)! No options! -->";
                                }
                        $html .= '</legend>';
                        for ( $i = 1; $i <= $count; $i++ )
                            {
                            $html .= $this->display_options_sheet ( $i, (($i == $selected_option) ? 'block' : 'none') );
                            }
                    $html .= '</fieldset>';
                $html .= '</form>';
                $html .= '<div class="BMLTPlugin_toolbar_line_bottom">';
                    $html .= '<form action ="'.$this->get_admin_form_uri().'" method="post">';
                        $html .= '<div id="BMLTPlugin_bottom_button_div" class="BMLTPlugin_bottom_button_div">';
                            if ( $count > 1 )
                                {
                                $html .= '<div class="BMLTPlugin_toolbar_button_line_left">';
                                    $html .= '<script type="text/javascript">';
                                        $html .= "var c_g_delete_confirm_message='".$this->process_text ( self::$local_options_delete_option_confirm )."';";
                                    $html .= '</script>';
                                    $html .= '<input type="button" id="BMLTPlugin_toolbar_button_del" class="BMLTPlugin_delete_button" value="'.$this->process_text ( self::$local_options_delete_option ).'" onclick="BMLTPlugin_DeleteOptionSheet()" />';
                                $html .= '</div>';
                                }
                            
                            $html .= '<input type="submit" id="BMLTPlugin_toolbar_button_new" class="BMLTPlugin_create_button" name="BMLTPlugin_create_option" value="'.$this->process_text ( self::$local_options_add_new ).'" />';
                            
                            $html .= '<div class="BMLTPlugin_toolbar_button_line_right">';
                                $html .= '<input id="BMLTPlugin_toolbar_button_save" type="button" value="'.$this->process_text ( self::$local_options_save ).'" onclick="BMLTPlugin_SaveOptions()" />';
                            $html .= '</div>';
                        $html .= '</div>';
                    $html .= '</form>';
                $html .= '</div>';
                $html .= '<div class="BMLTPlugin_toolbar_line_map">';
                    $html .= '<h2 class="BMLTPlugin_map_label_h2">'.$this->process_text ( self::$local_options_map_label ).'</h2>';
                    $html .= '<div class="BMLTPlugin_Map_Div" id="BMLTPlugin_Map_Div"></div>';
                    $html .= '<script type="text/javascript">' . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "BMLTPlugin_DirtifyOptionSheet(true);" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');    // This sets up the "Save Changes" button as disabled.
                        // This is a trick I use to hide irrelevant content from non-JS browsers. The element is drawn, hidden, then uses JS to show. No JS, no element.
                        $html .= "document.getElementById('BMLTPlugin_options_container').style.display='block';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_no_name = '".$this->process_text ( self::$local_options_no_name_string )."';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_no_root = '".$this->process_text ( self::$local_options_no_root_server_string )."';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_no_search = '".$this->process_text ( self::$local_options_no_new_search_string )."';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_root_canal = '".self::$local_options_url_bad."';";
                        $html .= "var c_g_BMLTPlugin_success_message = '".$this->process_text ( self::$local_options_save_success )."';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_failure_message = '".$this->process_text ( self::$local_options_save_failure )."';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_success_time = ".intval ( self::$local_options_success_time ).";" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_failure_time = ".intval ( self::$local_options_failure_time ).";" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_unsaved_prompt = '".$this->process_text ( self::$local_options_unsaved_message )."';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_test_server_success = '".$this->process_text ( self::$local_options_test_server_success )."';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_test_server_failure = '".$this->process_text ( self::$local_options_test_server_failure )."';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var c_g_BMLTPlugin_coords = new Array();" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var g_BMLTPlugin_TimeToFade = ".intval ( self::$local_options_success_time ).";" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= "var g_BMLTPlugin_no_gkey_string = '".$this->process_text ( self::$local_options_no_gkey_string)."';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        if ( is_array ( $options_coords ) && count ( $options_coords ) )
                            {
                            foreach ( $options_coords as $value )
                                {
                                $html .= 'c_g_BMLTPlugin_coords[c_g_BMLTPlugin_coords.length] = {';
                                $f = true;
                                foreach ( $value as $key2 => $value2 )
                                    {
                                    if ( $f )
                                        {
                                        $f = false;
                                        }
                                    else
                                        {
                                        $html .= ',';
                                        }
                                    $html .= "'".htmlspecialchars ( $key2 )."':";
                                    $html .= "'".htmlspecialchars ( $value2 )."'";
                                    }
                                $html .= '};';
                                }
                            }
                        $url = $this->get_plugin_path();
                        $url = htmlspecialchars ( $url.'google_map_images' );
                        $html .= "var c_g_BMLTPlugin_admin_google_map_images = '$url';" . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                        $html .= 'BMLTPlugin_admin_load_map();' . (defined ( '_DEBUG_MODE_' ) ? "\n" : '');
                    $html .= '</script>';
                $html .= '</div>';
            $html .= '</div>';
        $html .= '</div>';
        
        return $html;
        }
            
    /************************************************************************************//**
    *   \brief This will return the HTML for one sheet of options in the admin page.        *
    *                                                                                       *
    *   \returns The XHTML to be displayed.                                                 *
    ****************************************************************************************/
    function display_options_sheet ($in_options_index = 1,  ///< The options index. If not given, the first (main) ones are used.
                                    $display_mode = 'none'  ///< If this page is to be displayed, make it 'block'.
                                    )
        {
        $ret = '';

        $in_options_index = intval ( $in_options_index );
        
        if ( ($in_options_index < 1) || ($in_options_index > $this->get_num_options()) )
            {
            echo "<!-- BMLTPlugin Warning (display_options_sheet)! $in_options_index is out of range! Using the first options. -->";
            $in_options_index = 1;
            }
        
        $options = $this->getBMLTOptions ( $in_options_index );
        
        if ( is_array ( $options ) && count ( $options ) && isset ( $options['id'] ) )
            {

            $ret .= '<div class="BMLTPlugin_option_sheet" id="BMLTPlugin_option_sheet_'.$in_options_index.'_div" style="display:'.htmlspecialchars ( $display_mode ).'">';
                $ret .= '<h2 class="BMLTPlugin_option_id_h2">'.$this->process_text ( self::$local_options_settings_id_prompt ).htmlspecialchars ( $options['id'] ).'</h2>';
                $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                    $id = 'BMLTPlugin_option_sheet_name_'.$in_options_index;
                    $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_name_label ).'</label>';
                        $string = (isset ( $options['setting_name'] ) && $options['setting_name'] ? $options['setting_name'] : $this->process_text ( self::$local_options_no_name_string ) );
                    $ret .= '<input class="BMLTPlugin_option_sheet_line_name_text" id="'.htmlspecialchars ( $id ).'" type="text" value="'.htmlspecialchars ( $string ).'"';
                    $ret .= ' onfocus="BMLTPlugin_ClickInText(this.id,\''.$this->process_text ( self::$local_options_no_name_string ).'\',false)"';
                    $ret .= ' onblur="BMLTPlugin_ClickInText(this.id,\''.$this->process_text ( self::$local_options_no_name_string ).'\',true)"';
                    $ret .= ' onchange="BMLTPlugin_DirtifyOptionSheet()" onkeyup="BMLTPlugin_DirtifyOptionSheet()" />';
                $ret .= '</div>';
                $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                    $id = 'BMLTPlugin_option_sheet_root_server_'.$in_options_index;
                    $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_rootserver_label ).'</label>';
                        $string = (isset ( $options['root_server'] ) && $options['root_server'] ? $options['root_server'] : $this->process_text ( self::$local_options_no_root_server_string ) );
                    $ret .= '<input class="BMLTPlugin_option_sheet_line_root_server_text" id="'.htmlspecialchars ( $id ).'" type="text" value="'.htmlspecialchars ( $string ).'"';
                    $ret .= ' onfocus="BMLTPlugin_ClickInText(this.id,\''.$this->process_text ( self::$local_options_no_root_server_string).'\',false)"';
                    $ret .= ' onblur="BMLTPlugin_ClickInText(this.id,\''.$this->process_text ( self::$local_options_no_root_server_string).'\',true)"';
                    $ret .= ' onchange="BMLTPlugin_DirtifyOptionSheet()" onkeyup="BMLTPlugin_DirtifyOptionSheet()" />';
                    $ret .= '<div class="BMLTPlugin_option_sheet_Test_Button_div">';
                        $ret .= '<input type="button" value="'.$this->process_text ( self::$local_options_test_server ).'" onclick="BMLTPlugin_TestRootUri_call()" title="'.$this->process_text ( self::$local_options_test_server_tooltip ).'" />';
                        $ret .= '<div class="BMLTPlugin_option_sheet_NEUT" id="BMLTPlugin_option_sheet_indicator_'.$in_options_index.'"></div>';
                        $ret .= '<div class="BMLTPlugin_option_sheet_Version" id="BMLTPlugin_option_sheet_version_indicator_'.$in_options_index.'"></div>';
                    $ret .= '</div>';
                $ret .= '</div>';
                $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                    $id = 'BMLTPlugin_option_sheet_new_search_'.$in_options_index;
                    $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_new_search_label ).'</label>';
                        $string = (isset ( $options['bmlt_new_search_url'] ) && $options['bmlt_new_search_url'] ? $options['bmlt_new_search_url'] : $this->process_text ( self::$local_options_no_new_search_string ) );
                    $ret .= '<input class="BMLTPlugin_option_sheet_line_new_search_text" id="'.htmlspecialchars ( $id ).'" type="text" value="'.htmlspecialchars ( $string ).'"';
                    $ret .= ' onfocus="BMLTPlugin_ClickInText(this.id,\''.$this->process_text ( self::$local_options_no_new_search_string).'\',false)"';
                    $ret .= ' onblur="BMLTPlugin_ClickInText(this.id,\''.$this->process_text ( self::$local_options_no_new_search_string).'\',true)"';
                    $ret .= ' onchange="BMLTPlugin_DirtifyOptionSheet()" onkeyup="BMLTPlugin_DirtifyOptionSheet()" />';
                $ret .= '</div>';
                $dir_res = opendir ( dirname ( __FILE__ ).'/themes' );
                if ( $dir_res )
                    {
                    $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                        $id = 'BMLTPlugin_option_sheet_theme_'.$in_options_index;
                        $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_theme_prompt ).'</label>';
                        $ret .= '<select id="'.htmlspecialchars ( $id ).'" onchange="BMLTPlugin_DirtifyOptionSheet()">';
                            while ( false !== ( $file_name = readdir ( $dir_res ) ) )
                                {
                                if ( !preg_match ( '/^\./', $file_name ) && is_dir ( dirname ( __FILE__ ).'/themes/'.$file_name ) )
                                    {
                                    $ret .= '<option value="'.htmlspecialchars ( $file_name ).'"';
                                    if ( $file_name == $options['theme'] )
                                        {
                                        $ret .= ' selected="selected"';
                                        }
                                    $ret .= '>'.htmlspecialchars ( $file_name ).'</option>';
                                    }
                                }
                        $ret .= '</select>';
                    $ret .= '</div>';
                    }
                $ret .= '<div class="BMLTPlugin_option_sheet_line_div BMLTPlugin_additional_css_line">';
                    $id = 'BMLTPlugin_option_sheet_additional_css_'.$in_options_index;
                    $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_more_styles_label ).'</label>';
                    $ret .= '<textarea class="BMLTPlugin_option_sheet_additional_css_textarea" id="'.htmlspecialchars ( $id ).'" onchange="BMLTPlugin_DirtifyOptionSheet()">';
                    $ret .= htmlspecialchars ( $options['additional_css'] );
                    $ret .= '</textarea>';
                $ret .= '</div>';
                $ret .= '<fieldset class="BMLTPlugin_option_sheet_mobile_settings_fieldset">';
                    $ret .= '<legend class="BMLTPlugin_gmap_caveat_legend">'.$this->process_text ( self::$local_options_mobile_legend ).'</legend>';
                    $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                        $id = 'BMLTPlugin_option_sheet_distance_units_'.$in_options_index;
                        $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_distance_prompt ).'</label>';
                        $ret .= '<select id="'.htmlspecialchars ( $id ).'" onchange="BMLTPlugin_DirtifyOptionSheet()">';
                            $ret .= '<option value="mi"';
                            if ( 'mi' == $options['distance_units'] )
                                {
                                $ret .= ' selected="selected"';
                                }
                            $ret .= '>'.$this->process_text ( self::$local_options_miles ).'</option>';
                            $ret .= '<option value="km"';
                            if ( 'km' == $options['distance_units'] )
                                {
                                $ret .= ' selected="selected"';
                                }
                            $ret .= '>'.$this->process_text ( self::$local_options_kilometers ).'</option>';
                        $ret .= '</select>';
                        $ret .= '<div class="BMLTPlugin_option_sheet_text_div">'.$this->process_text ( self::$local_options_distance_disclaimer ).'</div>';
                    $ret .= '</div>';
                    $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                        $id = 'BMLTPlugin_option_sheet_grace_period_'.$in_options_index;
                        $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_mobile_grace_period_label ).'</label>';
                        $ret .= '<select id="'.htmlspecialchars ( $id ).'" onchange="BMLTPlugin_DirtifyOptionSheet()">';
                            for ( $minute = 0; $minute < 60; $minute += 5 )
                                {
                                $ret .= '<option value="'.$minute.'"';
                                if ( $minute == $options['grace_period'] )
                                    {
                                    $ret .= ' selected="selected"';
                                    }
                                $ret .= '>'.$minute.'</option>';
                                }
                        $ret .= '</select>';
                        $ret .= '<div class="BMLTPlugin_option_sheet_text_div">'.$this->process_text ( self::$local_options_grace_period_disclaimer ).'</div>';
                    $ret .= '</div>';
                    $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                        $id = 'BMLTPlugin_option_sheet_time_offset_'.$in_options_index;
                        $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_mobile_time_offset_label ).'</label>';
                        $ret .= '<select id="'.htmlspecialchars ( $id ).'" onchange="BMLTPlugin_DirtifyOptionSheet()">';
                            for ( $hour = -23; $hour < 24; $hour++ )
                                {
                                $ret .= '<option value="'.$hour.'"';
                                if ( $hour == $options['time_offset'] )
                                    {
                                    $ret .= ' selected="selected"';
                                    }
                                $ret .= '>'.$hour.'</option>';
                                }
                        $ret .= '</select>';
                        $ret .= '<div class="BMLTPlugin_option_sheet_text_div">'.$this->process_text ( self::$local_options_time_offset_disclaimer ).'</div>';
                    $ret .= '</div>';
                $ret .= '</fieldset>';
                $ret .= '<fieldset class="BMLTPlugin_option_sheet_old_settings_fieldset">';
                    $ret .= '<legend class="BMLTPlugin_gmap_caveat_legend">'.$this->process_text ( self::$local_options_gkey_caveat ).'</legend>';
                    $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                        $id = 'BMLTPlugin_option_sheet_gkey_'.$in_options_index;
                        $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_gkey_label ).'</label>';
                            $string = (isset ( $options['gmaps_api_key'] ) && $options['gmaps_api_key'] ? $options['gmaps_api_key'] : $this->process_text ( self::$local_options_no_gkey_string ) );
                        $ret .= '<input class="BMLTPlugin_option_sheet_line_gkey_text" id="'.htmlspecialchars ( $id ).'" type="text" value="'.htmlspecialchars ( $string ).'"';
                        $ret .= ' onfocus="BMLTPlugin_ClickInText(this.id,\''.$this->process_text ( self::$local_options_no_gkey_string).'\',false)"';
                        $ret .= ' onblur="BMLTPlugin_ClickInText(this.id,\''.$this->process_text ( self::$local_options_no_gkey_string).'\',true)"';
                        $ret .= ' onchange="BMLTPlugin_DirtifyOptionSheet()" onkeyup="BMLTPlugin_DirtifyOptionSheet()" />';
                    $ret .= '</div>';
                    $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                        $id = 'BMLTPlugin_option_sheet_initial_view_'.$in_options_index;
                        $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_initial_view_prompt ).'</label>';
                        $ret .= '<select id="'.htmlspecialchars ( $id ).'" onchange="BMLTPlugin_DirtifyOptionSheet()">';
                            foreach ( self::$local_options_initial_view as $value => $prompt )
                                {
                                $ret .= '<option value="'.htmlspecialchars ( $value ).'"';
                                if ( $value == $options['bmlt_initial_view'] )
                                    {
                                    $ret .= ' selected="selected"';
                                    }
                                $ret .= '>'.$this->process_text ( $prompt ).'</option>';
                                }
                        $ret .= '</select>';
                    $ret .= '</div>';
                    $ret .= '<div class="BMLTPlugin_option_sheet_line_div">';
                        $id = 'BMLTPlugin_option_sheet_language_'.$in_options_index;
                        $ret .= '<input type="hidden" id="BMLTPlugin_option_sheet_language_name_'.$in_options_index.'" value="'.$this->process_text ( $options['lang_name'] ).'" />';
                        $ret .= '<label for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_language_prompt ).'</label>';
                        $ret .= '<select class="BMLTPlugin_lang_select" disabled="disabled" id="'.htmlspecialchars ( $id ).'" onchange="BMLTPlugin_ChangeLanguage()">';
                            $ret .= '<option value="'.htmlspecialchars ( $options['lang_enum'] ).'" selected="selected">'.$this->process_text ( $options['lang_name'] ).'</option>';
                        $ret .= '</select>';
                        $ret .= '<input class="BMLTPlugin_lang_button" type="button" value="'.$this->process_text ( self::$local_options_fetch_server_langs ).'" onclick="BMLTPlugin_FetchServerLangs('.$in_options_index.')" title="'.$this->process_text ( self::$local_options_fetch_server_langs_tooltip ).'" />';
                        $ret .= '<div class="BMLTPlugin_option_sheet_Server_Lang_Throbber" id="BMLTPlugin_option_sheet_Server_Lang_Throbber_'.$in_options_index.'"></div>';
                    $ret .= '</div>';
                    $ret .= '<div class="BMLTPlugin_option_sheet_line_div BMLTPlugin_special_check_div">';
                        $id = 'BMLTPlugin_option_sheet_push_down_'.$in_options_index;
                        $ret .= '<input class="BMLTPlugin_special_check" type="checkbox" id="'.htmlspecialchars ( $id ).'" onclick="BMLTPlugin_DirtifyOptionSheet()"';
                            if ( $options['push_down_more_details'] == '1' )
                                {
                                $ret .= ' checked="checked"';
                                }
                        $ret .= ' />';
                        $ret .= '<label class="BMLTPlugin_special_check_label" for="'.htmlspecialchars ( $id ).'">'.$this->process_text ( self::$local_options_push_down_checkbox_label ).'</label>';
                    $ret .= '</div>';
                $ret .= '</fieldset>';
            $ret .= '</div>';
            }
        else
            {
            echo "<!-- BMLTPlugin ERROR (display_options_sheet)! Options not found for $in_options_index! -->";
            }
        
        return $ret;
        }
        
    /************************************************************************************//**
    *                                   GENERIC HANDLERS                                    *
    ****************************************************************************************/
    
    /************************************************************************************//**
    *   \brief This does any admin actions necessary.                                       *
    ****************************************************************************************/
    function admin_ajax_handler ( )
        {
        // We only go here if we are in an AJAX call (This function dies out the session).
        if ( isset ( $this->my_http_vars['BMLTPlugin_Save_Settings_AJAX_Call'] ) )
            {
            $ret = 0;
            
            if ( isset ( $this->my_http_vars['BMLTPlugin_set_options'] ) )
                {
                $ret = 1;
                
                $num_options = $this->get_num_options();
                
                for ( $i = 1; $i <= $num_options; $i++ )
                    {
                    $options = $this->getBMLTOptions ( $i );
                    
                    if ( is_array ( $options ) && count ( $options ) )
                        {
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_name_'.$i] ) )
                            {
                            if ( trim ( $this->my_http_vars['BMLTPlugin_option_sheet_name_'.$i] ) )
                                {
                                $options['setting_name'] = trim ( $this->my_http_vars['BMLTPlugin_option_sheet_name_'.$i] );
                                }
                            else
                                {
                                $options['setting_name'] = '';
                                }
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_root_server_'.$i] ) )
                            {
                            if ( trim ( $this->my_http_vars['BMLTPlugin_option_sheet_root_server_'.$i] ) )
                                {
                                $options['root_server'] = trim ( $this->my_http_vars['BMLTPlugin_option_sheet_root_server_'.$i] );
                                }
                            else
                                {
                                $options['root_server'] = self::$default_rootserver;
                                }
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_new_search_'.$i] ) )
                            {
                            if ( trim ( $this->my_http_vars['BMLTPlugin_option_sheet_new_search_'.$i] ) )
                                {
                                $options['bmlt_new_search_url'] = trim ( $this->my_http_vars['BMLTPlugin_option_sheet_new_search_'.$i] );
                                }
                            else
                                {
                                $options['bmlt_new_search_url'] = self::$default_new_search;
                                }
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_initial_view_'.$i] ) )
                            {
                            if ( trim ( $this->my_http_vars['BMLTPlugin_option_sheet_initial_view_'.$i] ) )
                                {
                                $options['bmlt_initial_view'] = trim ( $this->my_http_vars['BMLTPlugin_option_sheet_initial_view_'.$i] );
                                }
                            else
                                {
                                $options['bmlt_initial_view'] = self::$default_initial_view;
                                }
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_theme_'.$i] ) )
                            {
                            if ( trim ( $this->my_http_vars['BMLTPlugin_option_sheet_theme_'.$i] ) )
                                {
                                $options['theme'] = trim ( $this->my_http_vars['BMLTPlugin_option_sheet_theme_'.$i] );
                                }
                            else
                                {
                                $options['theme'] = self::$default_theme;
                                }
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_gkey_'.$i] ) )
                            {
                            if ( trim ( $this->my_http_vars['BMLTPlugin_option_sheet_gkey_'.$i] ) )
                                {
                                $options['gmaps_api_key'] = trim ( $this->my_http_vars['BMLTPlugin_option_sheet_gkey_'.$i] );
                                }
                            else
                                {
                                $options['gmaps_api_key'] = self::$default_gkey;
                                }
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_additional_css_'.$i] ) )
                            {
                            if ( trim ( $this->my_http_vars['BMLTPlugin_option_sheet_additional_css_'.$i] ) )
                                {
                                $options['additional_css'] = trim ( $this->my_http_vars['BMLTPlugin_option_sheet_additional_css_'.$i] );
                                }
                            else
                                {
                                $options['additional_css'] = self::$default_additional_css;
                                }
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_push_down_'.$i] ) )
                            {
                            $options['push_down_more_details'] = $this->my_http_vars['BMLTPlugin_option_sheet_push_down_'.$i];
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_latitude_'.$i] ) && floatVal ( $this->my_http_vars['BMLTPlugin_option_latitude_'.$i] ) )
                            {
                            $options['map_center_latitude'] = floatVal ( $this->my_http_vars['BMLTPlugin_option_latitude_'.$i] );
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_longitude_'.$i] ) && floatVal ( $this->my_http_vars['BMLTPlugin_option_longitude_'.$i] ) )
                            {
                            $options['map_center_longitude'] = floatVal ( $this->my_http_vars['BMLTPlugin_option_longitude_'.$i] );
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_zoom_'.$i] ) && intval ( $this->my_http_vars['BMLTPlugin_option_zoom_'.$i] ) )
                            {
                            $options['map_zoom'] = floatVal ( $this->my_http_vars['BMLTPlugin_option_zoom_'.$i] );
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_language_'.$i] ) )
                            {
                            $options['lang_enum'] = $this->my_http_vars['BMLTPlugin_option_sheet_language_'.$i];
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_language_name_'.$i] ) )
                            {
                            $options['lang_name'] = $this->my_http_vars['BMLTPlugin_option_sheet_language_name_'.$i];
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_distance_units_'.$i] ) )
                            {
                            $options['distance_units'] = $this->my_http_vars['BMLTPlugin_option_sheet_distance_units_'.$i];
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_grace_period_'.$i] ) )
                            {
                            $options['grace_period'] = $this->my_http_vars['BMLTPlugin_option_sheet_grace_period_'.$i];
                            }
                        
                        if ( isset ( $this->my_http_vars['BMLTPlugin_option_sheet_time_offset_'.$i] ) )
                            {
                            $options['time_offset'] = $this->my_http_vars['BMLTPlugin_option_sheet_time_offset_'.$i];
                            }
                        
                        if ( !$this->setBMLTOptions ( $options, $i ) )
                            {
                            $ret = 0;
                            break;
                            }
                        }
                    }
                }
            
            ob_end_clean(); // Just in case we are in an OB
            die ( strVal ( $ret ) );
            }
        elseif ( isset ( $this->my_http_vars['BMLTPlugin_AJAX_Call'] ) || isset ( $this->my_http_vars['BMLTPlugin_Fetch_Langs_AJAX_Call'] ) )
            {
            $ret = '';
            if ( isset ( $this->my_http_vars['BMLTPlugin_AJAX_Call_Check_Root_URI'] ) )
                {
                $uri = trim ( $this->my_http_vars['BMLTPlugin_AJAX_Call_Check_Root_URI'] );
                
                $test = new bmlt_satellite_controller ( $uri );
                if ( $uri && ($uri != self::$local_options_no_root_server_string ) && $test instanceof bmlt_satellite_controller )
                    {
                    if ( !$test->get_m_error_message() )
                        {
                        if ( isset ( $this->my_http_vars['BMLTPlugin_AJAX_Call'] ) )
                            {
                            $ret = trim($test->get_server_version());
                            }
                        else
                            {
                            $slangs = $test->get_server_langs();
                            
                            if ( $slangs )
                                {
                                $langs = array();
                                foreach ( $slangs as $key => $value )
                                    {
                                    $langs[] = array ( $key, $value['name'], $value['default'] );
                                    }
                                
                                $ret = array2json ( $langs );
                                }
                            }
                        }
                    }
                }
            
            ob_end_clean(); // Just in case we are in an OB
            die ( $ret );
            }
        }
      
    /************************************************************************************//**
    *   \brief Handles some AJAX routes                                                     *
    *                                                                                       *
    *   This function is called after the page has loaded its custom fields, so we can      *
    *   figure out which settings we're using. If the settings support mobiles, and the UA  *
    *   indicates this is a mobile phone, we redirect the user to our fast mobile handler.  *
    ****************************************************************************************/
    function ajax_router ( )
        {
        // If this is a basic AJAX call, we drop out quickly (We're really just a router).
        if ( isset ( $this->my_http_vars['BMLTPlugin_mobile_ajax_router'] ) )
            {
            $options = $this->getBMLTOptions_by_id ( $this->my_http_vars['bmlt_settings_id'] ); // This is for security. We don't allow URIs to be directly specified. They must come from the settings.
            $uri = $options['root_server'].'/'.$this->my_http_vars['request'];
            ob_end_clean(); // Just in case we are in an OB
            die ( bmlt_satellite_controller::call_curl ( $uri ) );
            }
        else    // However, if it is a mobile call, we do the mobile thing, then drop out.
            {
            if ( isset ( $this->my_http_vars['BMLTPlugin_mobile'] ) )
                {
                $ret = $this->BMLTPlugin_fast_mobile_lookup ();
                
                ob_end_clean(); // Just in case we are in an OB
                
                $handler = null;
                
                if ( zlib_get_coding_type() === false )
                    {
                    $handler = "ob_gzhandler";
                    }
                
                ob_start($handler);
                    echo $ret;
                ob_end_flush();
                die ( );
                }
            else
                {
                if ( !isset ( $this->my_http_vars['bmlt_settings_id'] ) )
                    {
                    $this->my_http_vars['bmlt_settings_id'] = null; // Just to squash a warning.
                    }
                    
                $options = $this->getBMLTOptions_by_id ( $this->my_http_vars['bmlt_settings_id'] );
                
                $this->load_params ( );
                if ( isset ( $this->my_http_vars['redirect_ajax'] ) && $this->my_http_vars['redirect_ajax'] )
                    {
                    $url = $options['root_server']."/client_interface/xhtml/index.php?switcher=RedirectAJAX$this->my_params";
                    ob_end_clean(); // Just in case we are in an OB
                    $ret = bmlt_satellite_controller::call_curl ( $url );
                    
                    $handler = null;
                    
                    if ( zlib_get_coding_type() === false )
                        {
                        $handler = "ob_gzhandler";
                        }
                    
                    ob_start($handler);
                        echo $ret;
                    ob_end_flush();
                    die ( );
                    }
                elseif ( isset ( $this->my_http_vars['direct_simple'] ) )
                    {
                    $root_server = $options['root_server']."/client_interface/simple/index.php";
                    $params = urldecode ( $this->my_http_vars['search_parameters'] );
                    $url = "$root_server?switcher=GetSearchResults&".$params;
                    $result = bmlt_satellite_controller::call_curl ( $url );
                    $result = preg_replace ( '|\<a |', '<a rel="nofollow external" ', $result );
                    // What all this does, is pick out the single URI in the search parameters string, and replace the meeting details link with it.
                    if ( preg_match ( '|&single_uri=|', $this->my_http_vars['search_parameters'] ) )
                        {
                        $single_uri = '';
                        $sp = explode ( '&', $this->my_http_vars['search_parameters'] );
                        foreach ( $sp as $s )
                            {
                            if ( preg_match ( '|single_uri=|', $s ) )
                                {
                                list ( $key, $single_uri ) = explode ( '=', $s );
                                break;
                                }
                            }
                        if ( $single_uri )
                            {
                            $result = preg_replace ( '|\<a [^>]*href="'.preg_quote($options['root_server']).'.*?single_meeting_id=(\d+)[^>]*>|', "<a rel=\"nofollow\" title=\"".$this->process_text ( self::$local_single_meeting_tooltip)."\" href=\"".$single_uri."=$1&amp;supports_ajax=yes\">", $result );
                            }
                        $result = preg_replace ( '|\<a rel="external"|','<a rel="nofollow external" title="'.$this->process_text ( self::$local_gm_link_tooltip).'"', $result );
                        }

                    ob_end_clean(); // Just in case we are in an OB
                    
                    $handler = null;
                    
                    if ( zlib_get_coding_type() === false )
                        {
                        $handler = "ob_gzhandler";
                        }
                    
                    ob_start($handler);
                        echo $result;
                    ob_end_flush();
                    die ( );
                    }
                elseif ( isset ( $this->my_http_vars['result_type_advanced'] ) && ($this->my_http_vars['result_type_advanced'] == 'booklet') )
                    {
                    $uri =  $options['root_server']."/local_server/pdf_generator/?list_type=booklet$this->my_params";
                    ob_end_clean(); // Just in case we are in an OB
                    header ( "Location: $uri" );
                    die();
                    }
                elseif ( isset ( $this->my_http_vars['result_type_advanced'] ) && ($this->my_http_vars['result_type_advanced'] == 'listprint') )
                    {
                    $uri =  $options['root_server']."/local_server/pdf_generator/?list_type=listprint$this->my_params";
                    ob_end_clean(); // Just in case we are in an OB
                    header ( "Location: $uri" );
                    die();
                    }
                }
            }
        }
    
    /************************************************************************************//**
    *   \brief Massages the page content.                                                   *
    *                                                                                       *
    *   \returns a string, containing the "massaged" content.                               *
    ****************************************************************************************/
    function content_filter ( $in_the_content   ///< The content in need of filtering.
                            )
        {
        // Simple searches can be mixed in with other content.
        $in_the_content = $this->display_simple_search ( $in_the_content );

        $in_the_content = $this->display_old_search ( $in_the_content );
        
        return $in_the_content;
        }
        
    /************************************************************************************//**
    *   \brief This is a function that filters the content, and replaces a portion with the *
    *   "popup" search, if provided by the 'bmlt_simple_searches' custom field.             *
    *                                                                                       *
    *   \returns a string, containing the content.                                          *
    ****************************************************************************************/

    function display_popup_search ( $in_content,    ///< This is the content to be filtered.
                                    $in_text,       ///< The text that has the parameters in it.
                                    &$out_count     ///< This is set to 1, if a substitution was made.
                                    )
        {
        if ( $in_text && self::get_shortcode ( $in_content, 'simple_search_list' ) )
            {
            $display .= '';

            $text_ar = explode ( "\n", $in_text );
            
            if ( is_array ( $text_ar ) && count ( $text_ar ) )
                {
                $display .= '<noscript class="no_js">'.$this->process_text ( self::$local_noscript ).'</noscript>';
                $display .= '<div id="interactive_form_div" class="interactive_form_div" style="display:none"><form action="#" onsubmit="return false"><div>';
                $display .= '<label class="meeting_search_select_label" for="meeting_search_select">Find Meetings:</label> ';
                $display .= '<select id="meeting_search_select"class="simple_search_list" onchange="BMLTPlugin_simple_div_filler (this.value,this.options[this.selectedIndex].text);this.options[this.options.length-1].disabled=(this.selectedIndex==0)">';
                $display .= '<option disabled="disabled" selected="selected">'.$this->process_text ( self::$local_select_search ).'</option>';
                $lines_max = count ( $text_ar );
                $lines = 0;
                while ( $lines < $lines_max )
                    {
                    $line['parameters'] = trim($text_ar[$lines++]);
                    $line['prompt'] = trim($text_ar[$lines++]);
                    if ( $line['parameters'] && $line['prompt'] )
                        {
                        $uri = $this->get_ajax_base_uri().'?bmlt_settings_id='.$this->cms_get_page_settings_id($in_content).'&amp;direct_simple&amp;search_parameters='.urlencode ( $line['parameters'] );
                        $display .= '<option value="'.$uri.'">'.__($line['prompt']).'</option>';
                        }
                    }
                $display .= '<option disabled="disabled"></option>';
                $display .= '<option disabled="disabled" value="">'.$this->process_text ( self::$local_clear_search ).'</option>';
                $display .= '</select></div></form>';
                
                $display .= '<script type="text/javascript">';
                $display .= 'document.getElementById(\'interactive_form_div\').style.display=\'block\';';
                $display .= 'document.getElementById(\'meeting_search_select\').selectedIndex=0;';

                $options = $this->getBMLTOptions_by_id ( $this->cms_get_page_settings_id($in_content) );
                $url = $this->get_plugin_path();
                $img_url .= htmlspecialchars ( $url.'themes/'.$options['theme'].'/images/' );
                
                $display .= "var c_g_BMLTPlugin_images = '$img_url';";
                $display .= '</script>';
                $display .= '<div id="simple_search_container"></div></div>';
                }
            
            if ( $display )
                {
                $in_content = self::replace_shortcode ( $in_content, 'simple_search_list', $display );
            
                $out_count = 1;
                }
            }
        
        return $in_content;
        }
        
    /************************************************************************************//**
    *   \brief This is a function that filters the content, and replaces a portion with the *
    *   "classic" search.                                                                   *
    *                                                                                       *
    *   \returns a string, containing the content.                                          *
    ****************************************************************************************/
    function display_old_search ($in_content      ///< This is the content to be filtered.
                                 )
        {
        if ( self::get_shortcode ( $in_content, 'bmlt') ) 
            {
            $display = '';
            $options_id = $this->cms_get_page_settings_id( $in_content );
            
            $options = $this->getBMLTOptions_by_id ( $options_id );

            $this->my_http_vars['bmlt_settings_id'] = $options_id;
            
            $this->load_params();
            
            $root_server_root = $options['root_server'];

            if ( $root_server_root && $options['gmaps_api_key'] )
                {
                $root_server = $root_server_root."/client_interface/xhtml/index.php";
                
                $menu = '';
                    
                if ( !isset ( $this->my_http_vars['search_form'] ) && $options['bmlt_new_search_url'] )
                    {
                    if ( $options['bmlt_new_search_url'] )
                        {
                        $plink = $options['bmlt_new_search_url'];
                        }
                    $menu = '<div class="bmlt_menu_div no_print"><a rel="nofollow" href="'.htmlspecialchars($plink).'">'.$this->process_text ( self::$local_menu_new_search_text ).'</a></div>';
                    }
                
                if ( isset ( $this->my_http_vars['search_form'] ) )
                    {
                    $map_center = "&search_spec_map_center=".$options['map_center_latitude'].",".$options['map_center_longitude'].",".$options['map_zoom'];
                    $uri = "$root_server?switcher=GetSimpleSearchForm$this->my_params$map_center";
                    
                    if ( $options['lang_enum'] )
                        {
                        $uri .= '&lang_enum='.$options['lang_enum'];
                        }
                    
                    $the_new_content = bmlt_satellite_controller::call_curl ( $uri );
                    }
                elseif ( isset ( $this->my_http_vars['single_meeting_id'] ) && $this->my_http_vars['single_meeting_id'] )
                    {
                    $uri = "$root_server?switcher=GetOneMeeting&single_meeting_id=".$this->my_params;
                    $the_new_content = bmlt_satellite_controller::call_curl ( $uri );
                    }
                elseif ( isset ( $this->my_http_vars['do_search'] ) )
                    {
                    $uri = "$root_server?switcher=GetSearchResults".$this->my_params;
                    $the_new_content = bmlt_satellite_controller::call_curl ( $uri );
                    }
 
                $the_new_content = str_replace ( '###ROOT_SERVER###', $root_server_root, self::$local_no_js_warning ).$the_new_content;
                $the_new_content = '<div id="bmlt_container_div" class="bmlt_container_div"><table id="bmlt_container" cellspacing="0" cellpadding="0" class="bmlt_container_table"><tbody class="BMLTPlugin_container_tbody"><tr class="BMLTPlugin_container_tr"><td class="BMLTPlugin_container_td">'.$menu.'<div class="bmlt_content_div">'.$the_new_content.'</div>'.$menu.'</td></tr></tbody></table></div>';
                // We only allow one instance per page.

                $in_content = self::replace_shortcode ($in_content, 'bmlt', $the_new_content);
                
                $url = $this->get_plugin_path();
                
	            $in_content = str_replace ( $options['root_server'].'/client_interface/xhtml/../../themes/default/html/images/Throbber.gif', "$url/themes/".$options['theme']."/images/Throbber.gif", $in_content);
                }
            else
                {
                $the_new_content = $this->process_text ( self::$local_not_enough_for_old_style );

                $in_content = self::replace_shortcode ($in_content, 'bmlt', $the_new_content);
                }
            }
        
        return $in_content;
        }
        
    /************************************************************************************//**
    *   \brief This is a function that filters the content, and replaces a portion with the *
    *   "simple" search                                                                     *
    *                                                                                       *
    *   \returns a string, containing the content.                                          *
    ****************************************************************************************/
    function display_simple_search ($in_content      ///< This is the content to be filtered.
                                    )
        {
        $options_id = $this->cms_get_page_settings_id( $in_content );
        
        $options = $this->getBMLTOptions_by_id ( $options_id );
        $root_server_root = $options['root_server'];

        $in_content = str_replace ( '&#038;', '&', $in_content );   // This stupid kludge is because WordPress does an untoward substitution. Won't do anything unless WordPress has been naughty.
        while ( $params = self::get_shortcode ( $in_content, 'bmlt_simple' ) )
            {
            $param_array = explode ( '##-##', $params );    // You can specify a settings ID, by separating it from the URI parameters with a ##-##.
            
            $params = null;
            
            if ( is_array ( $param_array ) && (count ( $param_array ) > 1) )
                {
                $options = $this->getBMLTOptions_by_id ( $param_array[0] );
                $root_server_root = $options['root_server'];
                $params = '?'.$param_array[1];
                }
            else
                {
                $params = (count ($param_array) > 0) ? '?'.$param_array[0] : null;
                }
            
            $uri = $root_server_root."/client_interface/simple/index.php".$params;

            $the_new_content = bmlt_satellite_controller::call_curl ( $uri );
            $in_content = self::replace_shortcode ( $in_content, 'bmlt_simple', $the_new_content );
            }
        return $in_content;
        }

    /************************************************************************************//**
    *                              FAST MOBILE LOOKUP ROUTINES                              *
    *                                                                                       *
    *   Our mobile support is based on the fast mobile client. It has been adapted to fit   *
    *   into a WordPress environment.                                                       *
    ****************************************************************************************/

    /************************************************************************************//**
    *   \brief Checks the UA of the caller, to see if it should return XHTML Strict or WML. *
    *                                                                                       *
    *   NOTE: This is very, very basic. It is not meant to be a studly check, like WURFL.   *
    *                                                                                       *
    *   \returns A string. The DOCTYPE to be displayed.                                     *
    ****************************************************************************************/
    static function BMLTPlugin_select_doctype(  $in_http_vars   ///< The query variables
                                            )
        {
        $ret = '';
        
        function isDeviceWML1()
        {
            return BMLTPlugin::mobile_sniff_ua($in_http_vars) == 'wml';
        }
    
        function isDeviceWML2()
        {
            return BMLTPlugin::mobile_sniff_ua($in_http_vars) == 'xhtml_mp';
        }
            
        function isMobileDevice()
        {
            $language = BMLTPlugin::mobile_sniff_ua($in_http_vars);
            return ($language != 'xhtml') && ($language != 'smartphone');
        }
        
        // If we aren't deliberately forcing an emulation, we figure it out for ourselves.
        if ( !isset ( $in_http_vars['WML'] ) )
            {
            if ( isDeviceWML1() )
                {
                $in_http_vars['WML'] = 1;
                }
            elseif ( isDeviceWML2() )
                {
                $in_http_vars['WML'] = 2;
                }
            elseif ( isMobileDevice() )
                {
                $in_http_vars['WML'] = 1;
                }
            }
        
        // We may specify a mobile XHTML (WML 2) manually.
        if ( isset ( $in_http_vars['WML'] ) )
            {
            if ( $in_http_vars['WML'] == 2 )    // Use the XHTML MP header
                {
                $ret = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.0//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic10.dtd">';
                }
            else    // Default is WAP
                {
                $ret = '<'; // This is because some servers are dumb enough to interpret the embedded prolog as PHP delimiters.
                $ret .= '?xml version="1.0"?';
                $ret .= '><!DOCTYPE wml PUBLIC "-//WAPFORUM//DTD WML 1.1//EN" "http://www.wapforum.org/DTD/wml_1.1.xml">';
                }
            }
        else
            {
            // We return a fully-qualified XHTML 1.0 Strict page.
            $ret = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
            }
        
        if ( !isset ( $in_http_vars['WML'] ) || ($in_http_vars['WML'] != 1) )
            {
            $ret .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en"';
            if ( !isset ( $in_http_vars['WML'] ) )
                {
                $ret .= ' lang="en"';
                }
            $ret .= '>';
            }
        else
            {
            $ret .= '<wml>';
            }
        
        $ret .= '<head>';
    
        return $ret;
        }
    
    /************************************************************************************//**
    *   \brief Output the necessary Javascript. This is only called for a "pure javascript" *
    *   do_search invocation (smartphone interactive map).                                  *
    *                                                                                       *
    *   \returns A string. The XHTML to be displayed.                                       *
    ****************************************************************************************/
    function BMLTPlugin_fast_mobile_lookup_javascript_stuff( $in_sensor = true  ///< A Boolean. If false, then we will invoke the API with the sensor set false. Default is true.
                                                            )
        {
        $options = $this->getBMLTOptions_by_id ( $this->my_http_vars['bmlt_settings_id'] );
            
        $ret = '';
        $sensor = $in_sensor ? 'true' : 'false';

        // Include the Google Maps API V3 files.
        $ret .= '<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor='.$sensor.'"></script>';
        
        // Declare the various globals and display strings. This is how we pass strings to the JavaScript, as opposed to the clunky way we do it in the root server.
        $ret .= '<script type="text/javascript">';
        $ret .= 'var c_g_cannot_determine_location = \''.$this->process_text ( self::$local_cannot_determine_location ).'\';';
        $ret .= 'var c_g_no_meetings_found = \''.$this->process_text ( self::$local_mobile_fail_no_meetings ).'\';';
        $ret .= 'var c_g_server_error = \''.$this->process_text ( self::$local_server_fail ).'\';';
        $ret .= 'var c_g_address_lookup_fail = \''.$this->process_text ( self::$local_cant_find_address ).'\';';
        $ret .= 'var c_g_map_link_text = \''.$this->process_text ( self::$local_map_link ).'\';';
        $ret .= 'var c_g_weekdays = [';
        $ret .= "'".$this->process_text ( join ( "','", self::$local_weekdays ) )."'";
        $ret .= '];';
        $ret .= 'var c_g_formats = \''.$this->process_text ( self::$local_formats ).'\';';
        $ret .= 'var c_g_Noon = \''.$this->process_text ( self::$local_noon ).'\';';
        $ret .= 'var c_g_Midnight = \''.$this->process_text ( self::$local_midnight ).'\';';
        $ret .= 'var c_g_debug_mode = '.( defined ( 'DEBUG_MODE' ) ? 'true' : 'false' ).';';
        $h = null;
        $m = null;
        list ( $h, $m ) = explode ( ':', date ( "G:i", time() + ($options['time_offset'] * 60 * 60) - ($options['grace_time'] * 60) ) );
        $ret .= 'var c_g_hour = '.intval ( $h ).';';
        $ret .= 'var c_g_min = '.intval ( $m ).';';
        $ret .= 'var c_g_distance_prompt = \''.$this->process_text ( self::$local_mobile_distance ).'\';';
        $ret .= 'var c_g_distance_units_are_km = '.((strtolower ($options['distance_units']) == 'km' ) ? 'true' : 'false').';';
        $ret .= 'var c_g_distance_units = \''.((strtolower ($options['distance_units']) == 'km' ) ? $this->process_text ( self::$local_mobile_kilometers ) : $this->process_text ( self::$local_mobile_miles ) ).'\';';
        $ret .= 'var c_BMLTPlugin_files_uri = \''.htmlspecialchars ( $this->get_ajax_mobile_base_uri() ).'?\';';
        $ret .= 'var c_bmlt_settings_id='.$this->my_http_vars['bmlt_settings_id'].';';        
        $url = $this->get_plugin_path();

        $img_url = "$url/google_map_images";

        $img_url = htmlspecialchars ( $img_url );
        
        $ret .= "var c_g_BMLTPlugin_images = '$img_url';";
        $ret .= '</script>';
       
        if ( defined ( '_DEBUG_MODE_' ) ) // In debug mode, we use unoptimized versions of these files for easier tracking.
            {
            $ret .= '<script src="'.htmlspecialchars ( $url ).'fast_mobile_lookup.js" type="text/javascript"></script>';
            }
        else
            {
            $ret .= '<script src="'.htmlspecialchars ( $url ).'js_stripper.php?filename=fast_mobile_lookup.js" type="text/javascript"></script>';
            }

        return $ret;
        }
    
    /************************************************************************************//**
    *   \brief Output whatever header stuff is necessary for the available UA               *
    *                                                                                       *
    *   \returns A string. The XHTML to be displayed.                                       *
    ****************************************************************************************/
    function BMLTPlugin_fast_mobile_lookup_header_stuff()
        {
        $ret = '';
        $url = $this->get_plugin_path();
            
        $ret .= '<meta http-equiv="content-type" content="text/html; charset=utf-8" />';    // WML 1 only cares about the charset and cache.
        $ret .= '<meta http-equiv="Cache-Control" content="max-age=300"  />';               // Cache for 5 minutes.
        $ret .= '<meta http-equiv="Cache-Control" content="no-transform"  />';              // No Transforms.

        if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )   // If full XHTML
            {
            // Various meta tags we need.
            $ret .= '<meta http-equiv="Content-Script-Type" content="text/javascript" />';      // Set the types for inline styles and scripts.
            $ret .= '<meta http-equiv="Content-Style-Type" content="text/css" />';
            $ret .= '<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;" />'; // Make sure iPhone screens stay humble.
            
            $url = $this->get_plugin_path();
            
            $options = $this->getBMLTOptions_by_id ( $this->my_http_vars['bmlt_settings_id'] );
            
            $url = htmlspecialchars ( $url.'themes/'.$options['theme'].'/' );
            
            if ( defined ( '_DEBUG_MODE_' ) ) // In debug mode, we use unoptimized versions of these files for easier tracking.
                {
                $ret .= '<link rel="stylesheet" media="all" href="'.$url.'fast_mobile_lookup.css" type="text/css" />';
                }
            else
                {
                $ret .= '<link rel="stylesheet" media="all" href="'.htmlspecialchars($url).'style_stripper.php?filename=fast_mobile_lookup.css" type="text/css" />';
                }
            
            // If we have a shortcut icon, set it here.
            if ( defined ('_SHORTCUT_LOC_' ) )
                {
                $ret .= '<link rel="SHORTCUT ICON" href="'.$this->process_text ( _SHORTCUT_LOC_ ).'" />';
                }
            
            // Set the appropriate page title.
            if ( isset ( $this->my_http_vars['do_search'] ) )
                {
                $ret .= '<title>'.$this->process_text ( $local_mobile_results_page_title ).'</title>';
                }
            else
                {
                $ret .= '<title>'.$this->process_text ( $local_mobile_results_form_title ).'</title>';
                }
            }
        
        $ret .= '</head>';

        return $ret;
        }
    
    /************************************************************************************//**
    *   \brief Returns the XHTML/WML for the Map Search form. These are the three "fast     *
    *   lookup" links displayed at the top (Note display:none" in the style).               *
    *   This is to be revealed by JavaScript.                                               *
    *                                                                                       *
    *   \returns A string. The XHTML to be displayed.                                       *
    ****************************************************************************************/
    function BMLTPlugin_draw_map_search_form ()
        {
        $ret = '<div class="search_intro" id="hidden_until_js" style="display:none">';
            $ret .= '<h1 class="banner_h1">'.$this->process_text ( self::$local_GPS_banner ).'</h1>';
            $ret .= '<h2 class="banner_h2">'.$this->process_text ( self::$local_GPS_banner_subtext ).'</h2>';
            $ret .= '<div class="link_one_line"><a rel="nofollow" accesskey="1" href="'.htmlspecialchars ( $this->get_ajax_mobile_base_uri() ).'?BMLTPlugin_mobile&amp;do_search&amp;bmlt_settings_id='.$this->my_http_vars['bmlt_settings_id'].((isset ( $this->my_http_vars['base_url'] ) && $this->my_http_vars['base_url']) ? '&amp;base_url='.urlencode($this->my_http_vars['base_url']) : '').'">'.$this->process_text ( self::$local_search_all ).'</a></div>';
            $ret .= '<div class="link_one_line"><a rel="nofollow" accesskey="2" href="'.htmlspecialchars ( $this->get_ajax_mobile_base_uri() ).'?BMLTPlugin_mobile&amp;do_search&amp;qualifier=today&amp;bmlt_settings_id='.$this->my_http_vars['bmlt_settings_id'].((isset ( $this->my_http_vars['base_url'] ) && $this->my_http_vars['base_url']) ? '&amp;base_url='.urlencode($this->my_http_vars['base_url']) : '').'">'.$this->process_text ( self::$local_search_today ).'</a></div>';
            $ret .= '<div class="link_one_line"><a rel="nofollow" accesskey="3" href="'.htmlspecialchars ( $this->get_ajax_mobile_base_uri() ).'?BMLTPlugin_mobile&amp;do_search&amp;qualifier=tomorrow&amp;bmlt_settings_id='.$this->my_http_vars['bmlt_settings_id'].((isset ( $this->my_http_vars['base_url'] ) && $this->my_http_vars['base_url']) ? '&amp;base_url='.urlencode($this->my_http_vars['base_url']) : '').'">'.$this->process_text ( self::$local_search_tomorrow ).'</a></div>';
            $ret .= '<hr class="meeting_divider_hr" />';
        $ret .= '</div>';
        
        return $ret;
        }
    
    /************************************************************************************//**
    *   \brief Returns the XHTML/WML for the Address Entry form                             *
    *                                                                                       *
    *   \returns A string. The XHTML to be displayed.                                       *
    ****************************************************************************************/
    function BMLTPlugin_draw_address_search_form ()
        {
        if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )
            {
            $ret = '<form class="address_input_form" method="get" action="'.htmlspecialchars ( $this->get_ajax_mobile_base_uri() ).'"';
            if ( !isset ( $this->my_http_vars['WML'] ) )
                {
                // This fills the form with a "seed" text (standard accessibility practice). We do it this way, so we don't clutter the form if no JavaScript is available.
                $ret .= ' onsubmit="if((document.getElementById(\'address_input\').value==\'';
                $ret .= $this->process_text ( self::$local_enter_an_address );
                $ret .= '\')||!document.getElementById(\'address_input\').value){alert(\''.$this->process_text ( self::$local_enter_address_alert ).'\');document.getElementById(\'address_input\').focus();return false}else{if(document.getElementById(\'hidden_until_js\').style.display==\'block\'){document.getElementById(\'do_search\').value=\'1\'}}"';
                }
            $ret .= '>';
            $ret .= '<div class="search_address">';
            // The default, is we return a list. This is changed by JavaScript.
            $ret .= '<input type="hidden" name="BMLTPlugin_mobile" />';
            
            if ( isset ( $this->my_http_vars['base_url'] ) && $this->my_http_vars['base_url'] )
                {
                $ret .= '<input type="hidden" name="base_url" value="'.htmlspecialchars($this->my_http_vars['base_url']).'" />';
                }
                
            $ret .= '<input type="hidden" name="bmlt_settings_id" value="'.$this->my_http_vars['bmlt_settings_id'].'" />';
            $ret .= '<input type="hidden" name="do_search" id="do_search" value="the hard way" />';
            $ret .= '<h1 class="banner_h2">'.$this->process_text ( self::$local_search_address_single ).'</h1>';
            if ( !isset ( $this->my_http_vars['WML'] ) )  // This is here to prevent WAI warnings.
                {
                $ret .= '<label for="address_input" style="display:none">'.$this->process_text ( self::$local_enter_address_alert ).'</label>';
                }
            if ( isset ( $this->my_http_vars['WML'] ) )
                {
                $ret .= '<input type="hidden" name="WML" value="2" />';
                }
            }
        else
            {
            $ret = '<p>';   // WML rides the short bus.
            }
        
        if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )
            {
            $ret .= '<div class="address_top" id="address_input_line_wrapper">';
            $ret .= '<div class="link_one_line input_line_div" id="address_input_line_div">';
            if ( !isset ( $this->my_http_vars['WML'] ) )
                {
                $ret .= '<div class="link_one_line" id="hidden_until_js2" style="display:none">';
                $ret .= '<input type="checkbox" id="force_list_checkbox"';
                $ret .= ' onchange="if(this.checked){document.getElementById ( \'hidden_until_js\' ).style.display = \'none\';document.getElementById(\'address_input\').focus();}else{document.getElementById ( \'hidden_until_js\' ).style.display = \'block\'}" /><label for="force_list_checkbox"';
                $ret .= '> '.$this->process_text ( self::$local_list_check ).'</label>';
                $ret .= '</div>';
                }
            $ret .= '</div>';
            }
            
        $ret .= '<input type="text" name="address"';
        
        if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )
            {
            $ret .= ' id="address_input" class="address_input" size="64" value=""';
            if ( !isset ( $this->my_http_vars['WML'] ) )
                {
                $ret .= ' onfocus="if(!this.value||(this.value==\''.$this->process_text ( self::$local_enter_an_address ).'\'))this.value=\'\'"';
                $ret .= ' onkeydown="if(!this.value||(this.value==\''.$this->process_text ( self::$local_enter_an_address ).'\'))this.value=\'\'"';
                $ret .= ' onblur="if(!this.value)this.value=\''.$this->process_text ( self::$local_enter_an_address ).'\'"';
                }
            }
        else
            {
            $ret .= ' size="32" format="*m"';
            }
        
        $ret .= ' />';
        
        if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )
            {
            $ret .= '</div>';
            }
        else
            {
            $ret .= '</p>';
            }
        
        if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )
            {
            $ret .= '<div class="link_form_elements">';
            $ret .= '<div class="link_one_line">';
            $ret .= '<input checked="checked" id="search_all_days" type="radio" name="qualifier" value="" />';
            $ret .= '<label for="search_all_days"> '.$this->process_text ( self::$local_search_all_address ).'</label>';
            $ret .= '</div>';
            $ret .= '<div class="link_one_line">';
            $ret .= '<input id="search_today" type="radio" name="qualifier" value="today" />';
            $ret .= '<label for="search_today"> '.$this->process_text ( self::$local_search_today ).'</label>';
            $ret .= '</div>';
            $ret .= '<div class="link_one_line">';
            $ret .= '<input id="search_tomorrow" type="radio" name="qualifier" value="tomorrow" />';
            $ret .= '<label for="search_tomorrow"> '.$this->process_text ( self::$local_search_tomorrow ).'</label>';
            $ret .= '</div>';
            $ret .= '</div>';
            $ret .= '<div class="link_one_line_submit">';
            if ( !isset ( $this->my_http_vars['WML'] ) )  // This silly thing is to prevent WAI warnings.
                {
                $ret .= '<label for="submit_button" style="display:none">'.$this->process_text ( _SEARCH_SUBMIT_ ).'</label>';
                }
            $ret .= '<input id="submit_button" type="submit" value="'.$this->process_text ( self::$local_search_submit_button ).'"';
            if ( !isset ( $this->my_http_vars['WML'] ) )
                {
                $ret .= ' onclick="if((document.getElementById(\'address_input\').value==\'';
                $ret .= $this->process_text ( self::$local_enter_an_address );
                $ret .= '\')||!document.getElementById(\'address_input\').value){alert(\''.$this->process_text ( self::$local_enter_address_alert ).'\');document.getElementById(\'address_input\').focus();return false}else{if(document.getElementById(\'hidden_until_js\').style.display==\'block\'){document.getElementById(\'do_search\').value=\'1\'}}"';
                }
            $ret .= ' />';
            $ret .= '</div>';
            $ret .= '</div>';
            $ret .= '</form>';
            }
        else
            {
            $ret .= '<p>';
            $ret .= '<select name="qualifier" value="">';
            $ret .= '<option value="">'.$this->process_text ( self::$local_search_all_address ).'</option>';
            $ret .= '<option value="today">'.$this->process_text ( self::$local_search_today ).'</option>';
            $ret .= '<option value="tomorrow">'.$this->process_text ( self::$local_search_tomorrow ).'</option>';
            $ret .= '</select>';
            $ret .= '<anchor>';
            $ret .= '<go href="'.htmlspecialchars ( $this->get_ajax_mobile_base_uri() ).'" method="get">';
            $ret .= '<postfield name="address" value="$(address)"/>';
            $ret .= '<postfield name="qualifier" value="$(qualifier)"/>';
            $ret .= '<postfield name="do_search" value="the hard way" />';
            $ret .= '<postfield name="WML" value="1" />';
            $ret .= '<postfield name="BMLTPlugin_mobile" value="1" />';
            if ( isset ( $this->my_http_vars['base_url'] ) && $this->my_http_vars['base_url'] )
                {
                $ret .= '<postfield type="hidden" name="base_url" value="'.htmlspecialchars($this->my_http_vars['base_url']).'" />';
                }
            $ret .= '<postfield name="bmlt_settings_id" value="'.$this->my_http_vars['bmlt_settings_id'].'" />';
            $ret .= '</go>';
            $ret .= $this->process_text ( $local_search_submit_button );
            $ret .= '</anchor>';
            $ret .= '</p>';
            }
        
        return $ret;
        }

    /************************************************************************************//**
    *   \brief Renders one WML card                                                         *
    *                                                                                       *
    *   \returns A string. The WML 1.1 to be displayed.                                     *
    ****************************************************************************************/

    function BMLTPlugin_render_card (   $ret,                   ///< The current XHTML tally (so we can count it).
                                        $index,                 ///< The page index of the meeting.
                                        $count,                 ///< The total number of meetings.
                                        $meeting                ///< The meeting data.
                                    )
                            
        {
        $ret .= '<card id="card_'.$index.'" title="'.htmlspecialchars($meeting['meeting_name']).'">';
        

        if ( $count > 1 )
            {
            $next_card = null;
            $prev_card = null;
            $myself = null;
            $vars = array();
            
            unset ( $_REQUEST['access_card'] );
            
            foreach ( $_REQUEST as $name => $val )
                {
                $text = urlencode ( $name ).'='.urlencode ( $val );
                array_push ( $vars, $text );
                }
            
            $myself = htmlspecialchars ( $this->get_ajax_mobile_base_uri() ).'?'.join ( '&amp;', $vars ).'&amp;access_card=';
        
            if ( $index < $count )
                {
                $next_card = $myself.strval($index + 1);
                }
            
            if ( $index > 1 )
                {
                $prev_card = $myself.strval($index - 1);
                }

            $ret .= '<p><table columns="3"><tr>';
            $ret .= '<td>';
            if ( $prev_card )
                {
                $ret .= '<small><anchor>'.$this->process_text (self::$local_prev_card).'<go href="'.$prev_card.'"/></anchor></small>';
                }
            $ret .= '</td><td>&nbsp;</td><td>';
            if ( $next_card )
                {
                $ret .= '<small><anchor>'.$this->process_text (self::$local_next_card).'<go href="'.$next_card.'"/></anchor></small>';
                }
            
            $ret .= '</td></tr></table></p>';
            }
    
        $ret .= '<p><big><strong>'.htmlspecialchars($meeting['meeting_name']).'</strong></big></p>';
        $ret .= '<p>'.$this->process_text ( self::$local_weekdays[$meeting['weekday_tinyint']] ).' '.htmlspecialchars ( date ( 'g:i A', strtotime ( $meeting['start_time'] ) ) ).'</p>';
        if ( $meeting['location_text'] )
            {
            $ret .= '<p><b>'.htmlspecialchars ( $meeting['location_text'] ).'</b></p>';
            }
        
        $ret .= '<p>';
        if ( $meeting['location_street'] )
            {
            $ret .= htmlspecialchars ( $meeting['location_street'] );
            }
        
        if ( $meeting['location_neighborhood'] )
            {
            $ret .= ' ('.htmlspecialchars ( $meeting['location_neighborhood'] ).')';
            }
        $ret .= '</p>';
        
        if ( $meeting['location_municipality'] )
            {
            $ret .= '<p>'.htmlspecialchars ( $meeting['location_municipality'] );
        
            if ( $meeting['location_province'] )
                {
                $ret .= ', '.htmlspecialchars ( $meeting['location_province'] );
                }
            
            if ( $meeting['location_postal_code_1'] )
                {
                $ret .= ' '.htmlspecialchars ( $meeting['location_postal_code_1'] );
                }
            $ret .= '</p>';
            }
        
        $distance = null;
        
        if ( $meeting['distance_in_km'] )
            {
            $distance = round ( ((strtolower ($options['distance_units']) == 'km') ? $meeting['distance_in_km'] : $meeting['distance_in_miles']), 1 );
            
            $distance = strval ($distance).' '.((strtolower ($options['distance_units']) == 'km' ) ? $this->process_text ( self::$local_mobile_kilometers ) : $this->process_text ( self::$local_mobile_miles ) );

            $ret .= '<p><b>'.$this->process_text ( self::$local_mobile_distance ).':</b> '.htmlspecialchars ( $distance ).'</p>';
            }
        
        $ret .= '<p><b>'.$this->process_text ( self::$local_formats ).':</b> '.htmlspecialchars ( $meeting['formats'] ).'</p>';
        
        $ret .= '</card>';
        
        return $ret;
        }

    /************************************************************************************//**
    *   \brief Runs the lookup.                                                             *
    *                                                                                       *
    *   \returns A string. The XHTML to be displayed.                                       *
    ****************************************************************************************/
    function BMLTPlugin_fast_mobile_lookup()
        {
        /************************************************************************************//**
        *   \brief Sorting Callback                                                             *
        *                                                                                       *
        *   This will sort meetings by weekday, then by distance, so the first meeting of any   *
        *   given weekday is the closest one, etc.                                              *
        *                                                                                       *
        *   \returns -1 if a < b, 1, otherwise.                                                 *
        ****************************************************************************************/
        function mycmp (    $in_a_meeting,  ///< These are meeting data arrays. The elements we'll be checking will be 'weekday_tinyint' and 'distance_in_XX'.
                            $in_b_meeting
                        )
            {
            $ret = 0;
            
            if ( $in_a_meeting['weekday_tinyint'] != $in_b_meeting['weekday_tinyint'] )
                {
                $ret = ($in_a_meeting['weekday_tinyint'] < $in_b_meeting['weekday_tinyint']) ? -1 : 1;
                }
            else
                {
                $dist_a = intval ( round (strtolower(($options['distance_units']) == 'mi') ? $in_a_meeting['distance_in_miles'] : $in_a_meeting['distance_in_km'], 1) * 10 );
                $dist_b = intval ( round ((strtolower($options['distance_units']) == 'mi') ? $in_b_meeting['distance_in_miles'] : $in_b_meeting['distance_in_km'], 1) * 10 );
    
                if ( $dist_a != $dist_b )
                    {
                    $ret = ($dist_a < $dist_b) ? -1 : 1;
                    }
                else
                    {
                    $time_a = preg_replace ( '|:|', '', $in_a_meeting['start_time']);
                    $time_b = preg_replace ( '|:|', '', $in_b_meeting['start_time']);
                    $ret = ($time_a < $time_b) ? -1 : 1;
                    }
                }
            
            return $ret;
            }
        $ret = self::BMLTPlugin_select_doctype($this->my_http_vars);
        $ret .= $this->BMLTPlugin_fast_mobile_lookup_header_stuff();   // Add styles and/or JS, depending on the UA.
        $options = $this->getBMLTOptions_by_id ( $this->my_http_vars['bmlt_settings_id'] );
        
        // If we are running XHTML, then JavaScript works. Let's see if we can figure out where we are...
        // If the client can handle JavaScript, then the whole thing can be done with JS, and there's no need for the driver.
        // Also, if JS does not work, the form will ask us to do it "the hard way" (i.e. on the server).
        if ( $this->my_http_vars['address'] && isset ( $this->my_http_vars['do_search'] ) && (($this->my_http_vars['do_search'] == 'the hard way') || (isset ( $this->my_http_vars['WML'] ) && ($this->my_http_vars['WML'] == 1))) )
            {
            if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )   // Regular XHTML requires a body element.
                {
                $ret .= '<body>';
                }
            
            $this->my_driver->set_m_root_uri ( $options['root_server'] );
            $error = $this->my_driver->get_m_error_message();
            
            if ( $error )
                {
                ob_end_clean(); // Just in case we are in an OB
                die ( '<h1>ERROR (BMLTPlugin_fast_mobile_lookup: '.htmlspecialchars ( $error ).')</h1>' );
                }
            
            $qualifier = strtolower ( trim ( $this->my_http_vars['qualifier'] ) );
            
            // Do the search.
            
            if ( $this->my_http_vars['address'] )
                {
                $this->my_driver->set_current_transaction_parameter ( 'SearchString', $this->my_http_vars['address'] );
                $error_message = $this->my_driver->get_m_error_message();
                if ( $error_message )
                    {
                    $ret .= $this->process_text ( self::$local_server_fail ).' "'.htmlspecialchars ( $error_message ).'"';
                    }
                else
                    {
                    $this->my_driver->set_current_transaction_parameter ( 'StringSearchIsAnAddress', true );
                    $error_message = $this->my_driver->get_m_error_message();
                    if ( $error_message )
                        {
                        $ret .= $this->process_text ( self::$local_server_fail ).' "'.htmlspecialchars ( $error_message ).'"';
                        }
                    else
                        {
                        if ( $qualifier )
                            {
                            $weekdays = '';
                            $h = 0;
                            $m = 0;
                            $time = time() + ($options['time_offset'] * 60 * 60);
                            $today = intval(date ( "w", $time )) + 1;
                            // We set the current time, minus the grace time. This allows us to be running late, yet still have the meeting listed.
                            list ( $h, $m ) = explode ( ':', date ( "G:i", time() - ($options['grace_period'] * 60) ) );
                            if ( $qualifier == 'today' )
                                {
                                $weekdays = strval ($today);
                                }
                            else
                                {
                                $weekdays = strval ( ($today < 7) ? $today + 1 : 1 );
                                }
                            $this->my_driver->set_current_transaction_parameter ( 'weekdays', array($weekdays) );
                            $error_message = $this->my_driver->get_m_error_message();
                            if ( $error_message )
                                {
                                $ret .= $this->process_text ( self::$local_server_fail ).' "'.htmlspecialchars ( $error_message ).'"';
                                }
                            else
                                {
                                if ( $h || $m )
                                    {
                                    $this->my_driver->set_current_transaction_parameter ( 'StartsAfterH', $h );
                                    $error_message = $this->my_driver->get_m_error_message();
                                    if ( $error_message )
                                        {
                                        $ret .= $this->process_text ( self::$local_server_fail ).' "'.htmlspecialchars ( $error_message ).'"';
                                        }
                                    else
                                        {
                                        $this->my_driver->set_current_transaction_parameter ( 'StartsAfterM', $m );
                                        $error_message = $this->my_driver->get_m_error_message();
                                        if ( $error_message )
                                            {
                                            $ret .= $this->process_text ( self::$local_server_fail ).' "'.htmlspecialchars ( $error_message ).'"';
                                            }
                                        }
                                    }
                                }
                            }
                        
                        if ( $error_message )
                            {
                            $ret .= $this->process_text ( self::$local_server_fail ).' "'.htmlspecialchars ( $error_message ).'"';
                            }
                        else
                            {
                            $this->my_driver->set_current_transaction_parameter ( 'SearchStringRadius', -10 );
                            $error_message = $this->my_driver->get_m_error_message();
                            if ( $error_message )
                                {
                                $ret .= $this->process_text ( self::$local_server_fail ).' "'.htmlspecialchars ( $error_message ).'"';
                                }
                            else    // The search is set up. Throw the switch, Igor! ...yeth...mawther....
                                {
                                $search_result = $this->my_driver->meeting_search();

                                $error_message = $this->my_driver->get_m_error_message();
                                if ( $error_message )
                                    {
                                    $ret .= $this->process_text ( self::$local_server_fail ).' "'.htmlspecialchars ( $error_message ).'"';
                                    }
                                elseif ( isset ( $search_result ) && is_array ( $search_result ) && isset ( $search_result['meetings'] ) )
                                    {
                                    // Yes! We have valid search data!
                                    if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )   // Regular XHTML
                                        {
                                        $ret .= '<div class="multi_meeting_div">';
                                        }
                                    
                                    $index = 1;
                                    $count = count ( $search_result['meetings'] );
                                    usort ( $search_result['meetings'], 'mycmp' );
                                    if ( isset ( $_REQUEST['access_card'] ) && intval ( $_REQUEST['access_card'] ) )
                                        {
                                        $index = intval ( $_REQUEST['access_card'] );
                                        }
                                        
                                    if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )   // Regular XHTML
                                        {
                                        $index = 1;
                                        foreach ( $search_result['meetings'] as $meeting )
                                            {
                                            $ret .= '<div class="single_meeting_div">';
                                            $ret .= '<h1 class="meeting_name_h2">'.htmlspecialchars($meeting['meeting_name']).'</h1>';
                                            $ret .= '<p class="time_day_p">'.$this->process_text ( self::$local_weekdays[$meeting['weekday_tinyint']] ).' ';
                                            $time = explode ( ':', $meeting['start_time'] );
                                            $am_pm = ' AM';
                                            $distance = null;
                                            
                                            if ( $meeting['distance_in_km'] )
                                                {
                                                $distance = round ( ((strtolower ($options['distance_units']) == 'km') ? $meeting['distance_in_km'] : $meeting['distance_in_miles']), 1 );
                                                
                                                $distance = strval ($distance).' '.((strtolower ($options['distance_units']) == 'km' ) ? $this->process_text ( self::$local_mobile_kilometers ) : $this->process_text ( self::$local_mobile_miles ) );
                                                }

                                            $time[0] = intval ( $time[0] );
                                            $time[1] = intval ( $time[1] );
                                            
                                            if ( ($time[0] == 23) && ($time[1] > 50) )
                                                {
                                                $ret .= $this->process_text ( self::$local_midnight );
                                                }
                                            elseif ( ($time[0] == 12) && ($time[1] == 0) )
                                                {
                                                $ret .= $this->process_text ( self::$local_noon );
                                                }
                                            else
                                                {
                                                if ( ($time[0] > 12) || (($time[0] == 12) && ($time[1] > 0)) )
                                                    {
                                                    $am_pm = ' PM';
                                                    }
                                                
                                                if ( $time[0] > 12 )
                                                    {
                                                    $time[0] -= 12;
                                                    }
                                            
                                                if ( $time[1] < 10 )
                                                    {
                                                    $time[1] = "0$time[1]";
                                                    }
                                                
                                                $ret .= htmlspecialchars ( $time[0].':'.$time[1].$am_pm );
                                                }
                                            
                                            $ret .= '</p>';
                                            if ( $meeting['location_text'] )
                                                {
                                                $ret .= '<p class="locations_text_p">'.htmlspecialchars ( $meeting['location_text'] ).'</p>';
                                                }
                                            
                                            $ret .= '<p class="street_p">';
                                            if ( $meeting['location_street'] )
                                                {
                                                $ret .= htmlspecialchars ( $meeting['location_street'] );
                                                }
                                            
                                            if ( $meeting['location_neighborhood'] )
                                                {
                                                $ret .= '<span class="neighborhood_span"> ('.htmlspecialchars ( $meeting['location_neighborhood'] ).')</span>';
                                                }
                                            $ret .= '</p>';
                                            
                                            if ( $meeting['location_municipality'] )
                                                {
                                                $ret .= '<p class="town_p">'.htmlspecialchars ( $meeting['location_municipality'] );
                                            
                                                if ( $meeting['location_province'] )
                                                    {
                                                    $ret .= '<span class="state_span">, '.htmlspecialchars ( $meeting['location_province'] ).'</span>';
                                                    }
                                                
                                                if ( $meeting['location_postal_code_1'] )
                                                    {
                                                    $ret .= '<span class="zip_span"> '.htmlspecialchars ( $meeting['location_postal_code_1'] ).'</span>';
                                                    }
                                                $ret .= '</p>';
                                                if ( !isset ( $this->my_http_vars['WML'] ) )
                                                    {
                                                    $ret .= '<p id="maplink_'.intval($meeting['id_bigint']).'" style="display:none">';
                                                    $url = '';

                                                    $comma = false;
                                                    if ( $meeting['meeting_name'] )
                                                        {
                                                        $url .= urlencode($meeting['meeting_name']);
                                                        $comma = true;
                                                        }
                                                        
                                                    if ( $meeting['location_text'] )
                                                        {
                                                        $url .= ($comma ? ',+' : '').urlencode($meeting['location_text']);
                                                        $comma = true;
                                                        }
                                                    
                                                    if ( $meeting['location_street'] )
                                                        {
                                                        $url .= ($comma ? ',+' : '').urlencode($meeting['location_street']);
                                                        $comma = true;
                                                        }
                                                    
                                                    if ( $meeting['location_municipality'] )
                                                        {
                                                        $url .= ($comma ? ',+' : '').urlencode($meeting['location_municipality']);
                                                        $comma = true;
                                                        }
                                                        
                                                    if ( $meeting['location_province'] )
                                                        {
                                                        $url .= ($comma ? ',+' : '').urlencode($meeting['location_province']);
                                                        }
                                                    
                                                    $url = 'http://maps.google.com/maps?q='.urlencode($meeting['latitude']).','.urlencode($meeting['longitude']) . '+(%22'.str_replace ( "%28", '-', str_replace ( "%29", '-', $url )).'%22)';
                                                    $url .= '&ll='.urlencode($meeting['latitude']).','.urlencode($meeting['longitude']);
                                                    $ret .= '<a rel="external nofollow" accesskey="'.$index.'" href="'.htmlspecialchars ( $url ).'" title="'.htmlspecialchars($meeting['meeting_name']).'">'.$this->process_text ( self::$local_map_link ).'</a>';
                                                    $ret .= '<script type="text/javascript">document.getElementById(\'maplink_'.intval($meeting['id_bigint']).'\').style.display=\'block\';var c_BMLTPlugin_settings_id = '.htmlspecialchars ( $this->my_http_vars['bmlt_settings_id'] ).';</script>';

                                                    $ret .= '</p>';
                                                    }
                                                }
                                            
                                            if ( $distance )
                                                {
                                                $ret .= '<p class="distance_p"><strong>'.$this->process_text ( self::$local_mobile_distance ).':</strong> '.htmlspecialchars ( $distance ).'</p>';
                                                }
                                                
                                            $ret .= '<p class="formats_p"><strong>'.$this->process_text ( self::$local_formats ).':</strong> '.htmlspecialchars ( $meeting['formats'] ).'</p>';
                                            $ret .= '</div>';
                                            if ( $index++ < $count )
                                                {
                                                if ( !isset ( $this->my_http_vars['WML'] ) )
                                                    {
                                                    $ret .= '<hr class="meeting_divider_hr" />';
                                                    }
                                                else
                                                    {
                                                    $ret .= '<hr />';
                                                    }
                                                }
                                            }
                                        }
                                    else    // WML 1 (yuch) We do this, because we need to limit the size of the pages to fit simple phones.
                                        {
                                        $meetings = $search_result['meetings'];
                                        $indexed_array = array_values($meetings);
                                        $ret = $this->BMLTPlugin_render_card ( $ret, $index, $count, $indexed_array[$index - 1], false );
                                        }
                                    
                                    if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )   // Regular XHTML
                                        {
                                        $ret .= '</div>';
                                        }
                                    }
                                else
                                    {
                                    $ret .= '<h1 class="failed_search_h1';
                                    if ( isset ( $this->my_http_vars['WML'] ) && $this->my_http_vars['WML'] )   // We use a normally-positioned element in WML.
                                        {
                                        $ret .= '_wml';
                                        }
                                    $ret .= '">'.$this->process_text (self::$local_mobile_fail_no_meetings).'</h1>';
                                    }
                                }
                            }
                        }
                    }
                }
            else
                {
                $ret .= '<h1 class="failed_search_h1">'.$this->process_text (self::$local_enter_address_alert).'</h1>';
                }
            }
        elseif ( isset ( $this->my_http_vars['do_search'] ) && !((($this->my_http_vars['do_search'] == 'the hard way') || (isset ( $this->my_http_vars['WML'] ) && ($this->my_http_vars['WML'] == 1)))) )
            {
            $ret .= '<body id="search_results_body"';
            if ( !isset ( $this->my_http_vars['WML'] ) )
                {
                $ret .= ' onload="WhereAmI (\''.htmlspecialchars ( strtolower ( trim ( $this->my_http_vars['qualifier'] ) ) ).'\',\''.htmlspecialchars ( trim ( $this->my_http_vars['address'] ) ).'\')"';
                }
            $ret .= '>';

            $ret .= $this->BMLTPlugin_fast_mobile_lookup_javascript_stuff();

            $ret .= '<div id="location_finder" class="results_map_div">';
            
            $url = $this->get_plugin_path();
            
            $throbber_loc .= htmlspecialchars ( $url.'themes/'.$options['theme'].'/images/Throbber.gif' );
            
            $ret .= '<div class="throbber_div"><img id="throbber" src="'.htmlspecialchars ( $throbber_loc ).'" alt="AJAX Throbber" /></div>';
            $ret .= '</div>';
            }
        else
            {
            if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )
                {
                $ret .= '<body id="search_form_body"';
                if ( !isset ( $this->my_http_vars['WML'] ) )
                    {
                    $ret .= ' onload="if( (typeof ( navigator ) == \'object\' &amp;&amp; typeof ( navigator.geolocation ) == \'object\') || (window.blackberry &amp;&amp; blackberry.location.GPSSupported) || (typeof ( google ) == \'object\' &amp;&amp; typeof ( google.gears ) == \'object\') ){document.getElementById ( \'hidden_until_js\' ).style.display = \'block\';document.getElementById ( \'hidden_until_js2\' ).style.display = \'block\';};document.getElementById(\'address_input\').value=\''.$this->process_text ( self::$local_enter_an_address ).'\'"';
                    }
                $ret .= '>';
                $ret .= '<div class="search_div"';
                if ( !isset ( $this->my_http_vars['WML'] ) )
                    {
                    $ret .= ' cellpadding="0" cellspacing="0" border="0"';
                    }
                $ret .= '>';
                $ret .= '<div class="GPS_lookup_row_div"><div>';
                if ( !isset ( $this->my_http_vars['WML'] ) )
                    {
                    $ret .= $this->BMLTPlugin_draw_map_search_form ();
                    }
                $ret .= '</div></div>';
                $ret .= '<div><div>';
                $ret .= $this->BMLTPlugin_draw_address_search_form();
                $ret .= '</div></div></div>';
                }
            else
                {
                $ret .= '<card title="'.$this->process_text (_FORM_TITLE_).'">';
                $ret .= $this->BMLTPlugin_draw_address_search_form();
                $ret .= '</card>';
                }
            }
        
        if ( !isset ( $this->my_http_vars['WML'] ) || ($this->my_http_vars['WML'] != 1) )
            {
            $ret .= '</body>';  // Wrap up the page.
            $ret .= '</html>';
            if ( isset ( $this->my_http_vars['WML'] ) && ($this->my_http_vars['WML'] == 2) )
                {
                $ret = "<"."?xml version='1.0' encoding='UTF-8' ?".">".$ret;
                header ( 'Content-type: application/xhtml+xml' );
                }
            }
        else
            {
            $ret .= '</wml>';
            header ( 'Content-type: text/vnd.wap.wml' );
            }
        
        return $ret;
        }
    
    /************************************************************************************//**
    *                                    THE CONSTRUCTOR                                    *
    *                                                                                       *
    *   \brief Constructor. Enforces the SINGLETON, and sets up the callbacks.              *
    *                                                                                       *
    *   You will need to make sure that you call this with a parent::__construct() call.    *
    ****************************************************************************************/
    function __construct ()
        {
        if ( !isset ( self::$g_s_there_can_only_be_one ) || (self::$g_s_there_can_only_be_one === null) )
            {
            self::$g_s_there_can_only_be_one = $this;
            
            $this->my_http_vars = array_merge_recursive ( $_GET, $_POST );
                
            if ( !(isset ( $this->my_http_vars['search_form'] ) && $this->my_http_vars['search_form'] )
                && !(isset ( $this->my_http_vars['do_search'] ) && $this->my_http_vars['do_search'] ) 
                && !(isset ( $this->my_http_vars['single_meeting_id'] ) && $this->my_http_vars['single_meeting_id'] ) 
                )
                {
                $this->my_http_vars['search_form'] = true;
                }
            
            $this->my_http_vars['script_name'] = preg_replace ( '|(.*?)\?.*|', "$1", $_SERVER['REQUEST_URI'] );
            $this->my_http_vars['satellite'] = $this->my_http_vars['script_name'];
            $this->my_http_vars['supports_ajax'] = 'yes';
            $this->my_http_vars['no_ajax_check'] = 'yes';

            // We need to start off by setting up our driver.
            $this->my_driver = new bmlt_satellite_controller;
            
            if ( $this->my_driver instanceof bmlt_satellite_controller )
                {
                $this->set_callbacks(); // Set up the various callbacks and whatnot.
                }
            else
                {
                echo "<!-- BMLTPlugin ERROR (__construct)! Can't Instantiate the Satellite Driver! Please reinstall the plugin! -->";
                }
            }
        else
            {
            echo "<!-- BMLTPlugin Warning: __construct() called multiple times! -->";
            }
        }
    
    /************************************************************************************//**
    *                                THE CMS-SPECIFIC FUNCTIONS                             *
    *                                                                                       *
    * These need to be overloaded by the subclasses.                                        *
    ****************************************************************************************/
    
    /************************************************************************************//**
    *   \brief Return an HTTP path to the AJAX callback target for the mobile handler.      *
    *                                                                                       *
    *   \returns a string, containing the path. Defaults to the base URI.                   *
    ****************************************************************************************/
    protected function get_ajax_mobile_base_uri()
        {
        return $this->get_ajax_base_uri();
        }
    
    /************************************************************************************//**
    *   \brief Return an HTTP path to the AJAX callback target.                             *
    *                                                                                       *
    *   \returns a string, containing the path.                                             *
    ****************************************************************************************/
    protected function get_admin_ajax_base_uri()
        {
        return htmlspecialchars ( $this->get_ajax_base_uri() );
        }
    
    /************************************************************************************//**
    *   \brief Return an HTTP path to the basic admin form submit (action) URI              *
    *                                                                                       *
    *   \returns a string, containing the path.                                             *
    ****************************************************************************************/
    protected function get_admin_form_uri()
        {
        return null;
        }
    
    /************************************************************************************//**
    *   \brief Return an HTTP path to the AJAX callback target.                             *
    *                                                                                       *
    *   \returns a string, containing the path.                                             *
    ****************************************************************************************/
    protected function get_ajax_base_uri()
        {
        return $_SERVER['PHP_SELF'];
        }
    
    /************************************************************************************//**
    *   \brief Return an HTTP path to the plugin directory.                                 *
    *                                                                                       *
    *   \returns a string, containing the path.                                             *
    ****************************************************************************************/
    protected function get_plugin_path()
        {
        return null;
        }
    
    /************************************************************************************//**
    *   \brief This uses the WordPress text processor (__) to process the given string.     *
    *                                                                                       *
    *   This allows easier translation of displayed strings. All strings displayed by the   *
    *   plugin should go through this function.                                             *
    *                                                                                       *
    *   \returns a string, processed by WP.                                                 *
    ****************************************************************************************/
    protected function process_text (  $in_string  ///< The string to be processed.
                                    )
        {
        return null;
        }
        
    /************************************************************************************//**
    *   \brief Sets up the admin and handler callbacks.                                     *
    ****************************************************************************************/
    protected function set_callbacks ( )
        {
        }

    /************************************************************************************//**
    *   \brief This gets the admin options from the database (allows CMS abstraction).      *
    *                                                                                       *
    *   \returns an associative array, with the option settings.                            *
    ****************************************************************************************/
    protected function cms_get_option ( $in_option_key    ///< The name of the option
                                        )
        {
        return null;
        }
    
    /************************************************************************************//**
    *   \brief This gets the admin options from the database (allows CMS abstraction).      *
    ****************************************************************************************/
    protected function cms_set_option ( $in_option_key,   ///< The name of the option
                                        $in_option_value  ///< the values to be set (associative array)
                                        )
        {
        }
    
    /************************************************************************************//**
    *   \brief Deletes a stored option (allows CMS abstraction).                            *
    ****************************************************************************************/
    protected function cms_delete_option ( $in_option_key   ///< The name of the option
                                        )
        {
        }

    /************************************************************************************//**
    *   \brief This gets the page meta for the given page. (allows CMS abstraction).        *
    *                                                                                       *
    *   \returns a mixed type, with the meta data                                           *
    ****************************************************************************************/
    protected function cms_get_post_meta (  $in_page_id,    ///< The ID of the page/post
                                            $in_settings_id ///< The ID of the meta tag to fetch
                                            )
        {
        return null;
        }

    /************************************************************************************//**
    *   \brief This function fetches the settings ID for a page (if there is one).          *
    *                                                                                       *
    *   If $in_check_mobile is set to true, then ONLY a check for mobile support will be    *
    *   made, and no other shortcodes will be checked.                                      *
    *                                                                                       *
    *   \returns a mixed type, with the settings ID.                                        *
    ****************************************************************************************/
    protected function cms_get_page_settings_id ($in_content,               ///< Required (for the base version) content to check.
                                                 $in_check_mobile = false   ///< True if this includes a check for mobile. Default is false.
                                                )
        {
        $my_option_id = null;
        
        if ( $in_content )  // The default version requires content.
            {
            // We only return a mobile ID if we have the shortcode, we're asked for it, we're not already handling mobile, and we have a mobile UA.
            if ( $in_check_mobile && !isset ( $this->my_http_vars['BMLTPlugin_mobile'] ) && (self::mobile_sniff_ua ($this->my_http_vars) != 'xhtml') && ($params = self::get_shortcode ( $in_content, 'bmlt_mobile')) ) 
                {
                if ( $params === true ) // If no mobile settings number was provided, we use the default.
                    {
                    $options = $this->getBMLTOptions ( 1 );
                    $my_option_id = strval ( $options['id'] );
                    }
                else
                    {
                    $my_option_id = $params;
                    }
                }
            elseif( !$in_check_mobile ) // A mobile check ignores the rest.
                {
                if ( $params = self::get_shortcode ( $in_content, 'bmlt_simple') ) 
                    {
                    $param_array = explode ( '##-##', $params );
                    
                    if ( is_array ( $param_array ) && (count ( $param_array ) > 1) )
                        {
                        $my_option_id = $param_array[0];
                        }
                    }
        
                if ($params = self::get_shortcode ( $in_content, 'bmlt') ) 
                    {
                    $my_option_id = ( $params !== true ) ? $params : $my_option_id;
                    }
                }
            }
        
        return $my_option_id;
        }

    /************************************************************************************//**
    *                                  THE CMS CALLBACKS                                    *
    ****************************************************************************************/
        
    /************************************************************************************//**
    *   \brief Presents the admin page.                                                     *
    ****************************************************************************************/
    function admin_page ( )
        {
        }
       
    /************************************************************************************//**
    *   \brief Presents the admin menu options.                                             *
    *                                                                                       *
    * NOTE: This function requires WP. Most of the rest can probably be more easily         *
    * converted for other CMSes.                                                            *
    ****************************************************************************************/
    function option_menu ( )
        {
        }
        
    /************************************************************************************//**
    *   \brief Echoes any necessary head content.                                           *
    ****************************************************************************************/
    function standard_head ( )
        {
        }
        
    /************************************************************************************//**
    *   \brief Echoes any necessary head content for the admin.                             *
    ****************************************************************************************/
    function admin_head ( )
        {
        }
};
?>