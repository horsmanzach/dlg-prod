<?php 

// Completely disable magnific_popup.css
add_action('init', 'completely_disable_magnific_popup', 1);
function completely_disable_magnific_popup() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Block ALL magnific popup CSS requests site-wide
    if (strpos($request_uri, 'magnific_popup.css') !== false) {
        header('Content-Type: text/css');
        header('Cache-Control: max-age=86400'); // Cache for 24 hours
        echo '/* Magnific popup disabled site-wide */';
        exit;
    }
}


include_once("includes/crypt.php");
include_once("includes/hubspot.php");
include_once("includes/availability_check.php");
include_once("mpgClasses.php");
include_once("includes/ProcessPayment.php");

/* add_action( 'wp_enqueue_scripts', 'add_step8_script' );
function add_step8_script() {
	wp_enqueue_script( 'step8-script', get_stylesheet_directory_uri() . '/js/step8-script.js', array( 'jquery' ), '1.0', true );
}
*/ 


/*Fix Encoding Issue*/

// Fix for apostrophes and special characters in Divi modules
add_filter('do_shortcode_tag', 'fix_divi_special_characters', 10, 4);
function fix_divi_special_characters($output, $tag, $attr, $m) {
    // Check for various Divi modules that might have text with apostrophes
    if ('et_pb_text' === $tag || 'et_pb_button' === $tag || 'et_pb_toggle' === $tag 
        || 'et_pb_accordion_item' === $tag || 'et_pb_blurb' === $tag) {
        // Decode HTML entities to proper characters
        $output = html_entity_decode($output, ENT_QUOTES, 'UTF-8');
    }
    return $output;
}



function verify_card_ex($payment_info) {

	$mpg_response = VerifyCard( $payment_info );
	error_log( "Got verify response back " . $mpg_response->getComplete() );

	if( $mpg_response == false ||
		strcmp( $mpg_response->getComplete(), "true") ||
		$mpg_response->getResponseCode() == false ||
		$mpg_response->getResponseCode() == null ||
		$mpg_response->getResponseCode() >= 50 ) {

		$msg = "Invalid credit card number. Please double check the card number entered " . $mpg_response->getMessage();
		$response['status'] = "failed";
		$response['msg'] = $msg;
		$response['code'] = $mpg_response->getResponseCode();
		$response['ref'] = $mpg_response->getReferenceNum();

		$data_response = json_encode( $response );
		error_log( $data_response );
		die( $data_response );
	}

	error_log( "Got verify cvd result code " . $mpg_response->getCvdResultCode() );
	if( $mpg_response->getCvdResultCode() != "1M" ) {
		$response['status'] = "failed";

		$msg = "Invalid expiry date or CVV. Please double check the information entered " . $mpg_response->getMessage();

		$response['msg'] = $msg;
		$response['code'] = $mpg_response->getResponseCode();
		$response['ref'] = $mpg_response->getReferenceNum();

		$data_response = json_encode( $response );
		error_log( $data_response );
		die( $data_response );
	}

	error_log( "Got verify avs result code " . $mpg_response->getAvsResultCode() );
	if( $mpg_response->getAvsResultCode() == "N" ) {
		$response['status'] = "failed";

		$msg = "Invalid postal code. Please provide the billing postal code from your recent credit card statement (may be different from the service address postal code).";

		$response['msg'] = $msg;
		$response['code'] = $mpg_response->getResponseCode();
		$response['ref'] = $mpg_response->getReferenceNum();

		$data_response = json_encode( $response );
		error_log( $data_response );
		die( $data_response );
	}

	error_log( "Got response back " . $mpg_response->getComplete() );
}

/*================================================
#Load custom Contact Form Module
================================================*/
function divi_custom_contact_form() {
	get_template_part( '/includes/ContactForm' );
	$dcfm = new Custom_ET_Builder_Module_Contact_Form();
	remove_shortcode( 'et_pb_contact_form' );
	add_shortcode( 'et_pb_contact_form', array( $dcfm, '_render' ) );
}
add_action( 'et_builder_ready', 'divi_custom_contact_form' );

function divi_custom_contact_form_class( $classlist ) { 
    // Contact Form Module 'classname' overwrite. 
    $classlist['et_pb_contact_form'] = array( 'classname' => 'Custom_ET_Builder_Module_Contact_Form',); 
    return $classlist; 
} 

add_filter( 'et_module_classes', 'divi_custom_contact_form_class' );

function AddressCheckLog($state) {

	/*** Sending information to be saved ****/
	$d["status"] = 0;
	$d["magic"] = "Sl2soDSpLAsHqetS";
	$d["api"] = "1.00";
	$d["method"] = "addresscheck";
	$d["state"] = $state;
	
	$user_data = dg_get_current_user_data();
	$info = json_encode($user_data);

	$d["user_data"] = base64_encode( $info );
	$payload = json_encode($d);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://207.167.88.7/signup.php");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	// Set HTTP Header for POST request
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Content-Length: ' . strlen($payload))
	);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);

	error_log("Sending address check information to signup server, $info response $output ");

	return $output;
}

// Previous Developer Functions
function SendInfoToSignupServer ( $state ) {

	/*** Sending information to be saved ****/
	$d["status"] = 0;
	$d["magic"] = "Sl2soDSpLAsHqetS";
	$d["api"] = "1.00";
	$d["method"] = "newsignup";
	$d["state"] = $state;
	
	$user_data = dg_get_current_user_data();

	if( isset( $user_data["higheststate"] ) ) {
		$higheststate = intval( $user_data["higheststate"] );
		$state = intval( $state );
		if( $higheststate  < $state ) {
			$higheststate = $state;
			dg_set_user_meta("higheststate", $higheststate);
			$user_data = dg_get_current_user_data();
		}
	} else {
		dg_set_user_meta("higheststate", $state);
	}


	$user_data["ip"] = $_SERVER['REMOTE_ADDR'];

	$info = json_encode($user_data);
	//error_log("Sending user_data : $info ");
	
	$d["user_data"] = base64_encode( $info );


	if( $state <= 1 ){
	} else {
		$d["cart"] = base64_encode( json_encode( WC()->cart->get_cart() ));
		$d["monthly_summary"] =base64_encode( json_encode(  get_monthly_fee_summary() ));
		$d["upfront_summary"] = base64_encode( json_encode(  get_upfront_fee_summary() ));
	}

	$payload = json_encode($d);

	error_log("Sending to server payload : $payload");

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://207.167.88.7/signup.php");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	// Set HTTP Header for POST request
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Content-Length: ' . strlen($payload))
	);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);

	error_log("Sending information to signup server, response $output ");

	return $output;
}


add_action( 'wp_ajax_nopriv_complete_order', 'ajax_complete_order' );
add_action( 'wp_ajax_complete_order', 'ajax_complete_order' );
function ajax_complete_order() {

	error_log( "In ajax_complete_order ");
	if (isset($_POST['data']) && !empty($_POST['data'])) {

		// process the payment for the upfront fees
		$payment_info['type'] = 'purchase';

		$summary = get_upfront_fee_summary();
		$amount = $summary['grand_total'][1];

		$cust_id = dg_get_user_meta( "signup_id" );

		error_log(" ajax_complete_order $cust_id ");
		error_log(" ajax_complete_order $amount ");
		error_log(" ajax_complete_order $data.up");

		$card_number == "";
		$expdate = "";
		$upfront_bill_payment_option = "";

		foreach( $_POST['data'] as $key=>$value ) {

			dg_set_user_meta(sanitize_text_field($key),sanitize_text_field($value));
			if( strcmp( $key, "upfront_billing_card_number" ) == 0 ) {
				$card_number = Cleanup_Number( base64_decode( $value) ); 
				error_log( " Card number $card_number " );
			}

			if( strcmp( $key, "upfront_billing_card_expiry" ) == 0 ) {
				$expdate = Cleanup_Number( $value ); 
				$expdate = substr($expdate, 2) . substr( $expdate, 0, 2);
				error_log( " Card expre $expdate " );
			}

			if( strcmp( $key, "upfront_bill_payment_option" ) == 0 ) {
				$upfront_bill_payment_option = strtolower( trim($value) );
			}

			if( strcmp( $key, "upfront_billing_postcode" ) == 0 ) {
				$postal_code= strtolower(trim ($value) );
			}

			if( strcmp( $key, "upfront_billing_card_cvv" ) == 0 ) {
				$cvd = strtolower(trim ($value) );
			}

			error_log( "ajax_complete_order : $key, $value " ) ;
		}

		// clear data in DB
		dg_set_user_meta("upfront_bill_payment_option", $upfront_bill_payment_option);
		dg_set_user_meta("order_complete_timestamp", time() );
		dg_set_user_meta("upfront_payment_msg", "");
		dg_set_user_meta("upfront_payment_code", "" );
		dg_set_user_meta("upfront_payment_ref", "" );
		dg_set_user_meta("upfront_payment_amount", "" );
		dg_set_user_meta("upfront_payment_date", "" );
		dg_set_user_meta("upfront_payment_code", "" ) ;

		if( strcmp( $upfront_bill_payment_option, "email-transfer") == 0 ) {
			dg_set_user_meta("upfront_payment", "email-transfer"); // set this to a value so we confirm the signup finished

			SendInfoToSignupServer( 100 );

			$response['status'] = "success";
			$response['upfront'] = "email-transfer";
			$response['summary'] = base64_encode( RenderUserSummary() );

			$data_response = json_encode( $response );
			error_log( $data_response );
			die( $data_response  );
		}

		error_log("Sending verify information: $cust_id, $amount, $card_number, $expdate ");
        	$payment_info['custid'] = $cust_id;
 	       	$payment_info['orderid'] = 'verify-'.date("dmy-G:i:s");
        	$payment_info['amount'] = $amount; //'1.00';
	        $payment_info['cardno'] = $card_number; //'4242424242424242';
        	$payment_info['expdate'] = $expdate; //'2011';
        	$payment_info['postal_code'] = $postal_code;
        	$payment_info['cvd'] = $cvd;

		// verify before processing a transaction
		verify_card_ex( $payment_info );

		error_log(" Sending payment information: $cust_id, $amount, $card_number, $expdate ");
 	       	$payment_info['orderid'] = 'ord-'.date("dmy-G:i:s");
		$mpg_response = ProcessPayment( $payment_info );
		error_log( "Got Response back " . $mpg_response->getComplete() );
		if( $mpg_response == false || 
			strcmp( $mpg_response->getComplete(), "true") ||
			$mpg_response->getResponseCode() == false || 
			$mpg_response->getResponseCode() == null ||
			$mpg_response->getResponseCode() >= 50 ) {

			$response['status'] = "failed";
			$response['msg'] = $mpg_response->getMessage();
			$response['code'] = $mpg_response->getResponseCode();
			$response['ref'] = $mpg_response->getReferenceNum();
	
			$data_response = json_encode( $response );
			error_log( $data_response );
			die( $data_response  );
		} else {
			dg_set_user_meta("upfront_payment", $mpg_response->getComplete());
			dg_set_user_meta("order_complete_timestamp", time() );
			dg_set_user_meta("upfront_payment_msg", $mpg_response->getMessage() );
			dg_set_user_meta("upfront_payment_code", $mpg_response->getAuthCode() );
			dg_set_user_meta("upfront_payment_ref", $mpg_response->getReferenceNum() );
			dg_set_user_meta("upfront_payment_amount", $mpg_response->getTransAmount() );
			dg_set_user_meta("upfront_payment_date", $mpg_response->getTransDate() );
			dg_set_user_meta("upfront_payment_response_code", $mpg_response->getResponseCode() );
		
			SendInfoToSignupServer( 100 );

			$response['status'] = "success";
			$response['summary'] = base64_encode( RenderUserSummary() );

			$data_response = json_encode( $response );
			error_log( $data_response );
			die( $data_response  );
		}
			
	} else {
	
		die("Error");
	
	}
	
	die();

}

function RenderUserSummary() {


	return "Hello World!!";

}

add_action( 'wp_ajax_nopriv_find_address_signup', 'ajax_find_address_signup' );
add_action( 'wp_ajax_find_address_signup', 'ajax_find_address_signup' );
function ajax_find_address_signup( $ccd = false ) {

	if( isset( $_POST["ccd_param"] ) ) {
		$ccd = $_POST["ccd_param"];
		if( strlen( $ccd ) > 0 ) {
			dg_set_user_meta( "ccd", $ccd );
		}
	}

	$response = ppget_internet_plans(0, $ccd);
	$save_status_json = trim( SendInfoToSignupServer(1) );

	if( strlen( $save_status_json ) > 0 ) {
		$save_status = json_decode( $save_status_json );
		$status = trim( $save_status->status );
		if( strcmp( $status, "success" ) == 0 ) {
			//error_log( "FindAddressSignup, return new signup " . $status );
			if( $save_status->sid == "success") {
				dg_set_user_meta( "signup_id", "" );
			} else {
				dg_set_user_meta( "signup_id", $save_status->sid );
			}
		}
	}

	wp_die($response);
}

add_action( 'wp_ajax_nopriv_find_address', 'ajax_find_address' );
add_action( 'wp_ajax_find_address', 'ajax_find_address' );
function ajax_find_address( $ccd = false ) {
	
	$ccd = "";

	// getting the ccd parameters (referral to our website);
	if( isset( $_POST["ccd_param"] ) ) {
		$ccd = $_POST["ccd_param"];
		if( strlen( $ccd ) > 0 ) {
			dg_set_user_meta( "ccd", $ccd );
		}
	}

	error_log(" In ajax_find_address -> ppget_internet_plans $ccd ");
	
	$response = ppget_internet_plans(1,  $ccd);
	
	// commented out checking for email, to send the data anyways to the server for logging
	//$user_data = dg_get_current_user_data();
	//if( strlen( $user_data.email ) > 0 ) { 
		//AddressCheckLog( 0 );
	//}

	wp_die($response);
}

function ppget_internet_plans($MainWebPage, $ccd) {

	$prod_id_15_1 = 334;
	$prod_id_6 = 300;

	error_log("In ppget_internet_plans MainParam = $MainWebPage ");

	$ret_invald_address = "";
	$ret_diff_address = "";

	if( $MainWebPage == 1 ) {
		$ret_invald_address = "<a class=\"btn_mute plan_check_availability_btn\" href=\"#\" ";
		$ret_invald_address .= "data-target=\"#plan-building-wizard-modal\" data-toggle=\"modal\"";
		$ret_invald_address .= "rel=\"noopener noreferrer\">Check Service Availability in Your Area</a>";

		$ret_diff_address = "<a class=\"btn_mute plan_check_other_availability_btn\" href=\"#\" ";
		$ret_diff_address .= "data-target=\"#plan-building-wizard-modal\" data-toggle=\"modal\"";
		$ret_diff_address .= "rel=\"noopener noreferrer\">Search Different Address</a>";
	}

	if( isset( $_POST['streetAddress'] ) == true ) { // this is a new search
		//error_log("This is a new address " .  $_POST['streetAddress']  );

		$apiResponse = find_address_availability_ex();

	} else {
		$apiResponse = dg_get_user_meta("_api_response");
	}

	if( $apiResponse == null || $apiResponse["error"] == true ) {
		error_log("find_address_availability did not find the address - sending button to check avail");
		return $ret_invald_address;
	}

	error_log("In ppget_internet_plans " . json_encode( $apiResponse ) );
	//$ret_title = "<div class=\"internet-packages-mobile-section\">";
	$ret_title = "<div class=\"internet-packages-check-availability\">";
	$ret_title .= "<div class=\"internet-packages-location-button-group\"><div class=\"internet-packages-check-availability-title\">"; 
	$ret_title .= "<i class=\"fa fa-map-marker internet-packages-location-icon\" aria-hidden=\"true\"></i>"; 
	$ret_title .= "<small class=\"internet-packages-location\">  " . $apiResponse["address"] .  "</small></div>"; 
	$ret_title .= $ret_diff_address;
  	$ret_title .= "</div>";

	$selected_internet_plan = dg_get_user_meta("selected_internet_plan");

	if ( $apiResponse == NULL ) {
		error_log("No availability check done, need it first to get the plans ");
		return $ret_invald_address;
	}

	$query = new WC_Product_Query( array(
		'limit' => -1,
		'post_type' => array( 'product', 'product_variation' ),
		'orderby' => 'menu_order',
		'order' => 'DESC',
		'category' => array('internet-plan'),
	) );

	$products = $query->get_products();

	$button_text = '<a class="btn_mute" href="#" data-target="#plan-building-wizard-modal" rel="noopener noreferrer">Select Service</a>';
	
	$disply_prod = $ret_title;

	if($apiResponse["bell"] == true) {
		$bell_plans_avail = explode(",", trim( $apiResponse["bell_max_down"] ));
	}
	if($apiResponse["telus"] == true) {
		$telus_plans_avail = explode(",", trim( $apiResponse["telus_services"] ));
	}

	$ccd_clear = "";
	if( strlen( trim( $ccd ) )  > 0 ) {
		$ccd_clear = base64_decode ( trim( $ccd ) );
		error_log(" In ppget_internet_plans ---- $ccd = $ccd_clear ");

	}

	$disply_prod .= '<div class="box-radio internet-redio_box internet_plan_selector">';
	$found_one_match = false;
	$bell_plans_found = false;
	foreach ($products as $prod) {

		if ($prod->is_in_stock() == false ) {
			continue;
		}

		$prod_id_only = $prod->get_id();

		/* 
		 * Finding if this product is a special case product 15/1 and 6
		 * these needs to hide if there are higher speeds available
		 */
		$prod_sku = $prod->get_sku();
		$skus = explode(",",$prod_sku);
		foreach( $skus as $s ) {
			$s = trim($s);
			if( strcmp( $s, "GASR006008N") == 0 ) {
				$prod_id_6 = $prod_id_only; // this is a DSL 6 product
				break;
			}
			if( strcmp( $s, "GASR01501N") == 0 ) {
				$prod_id_15_1 = $prod_id_only; // this is a DSL 6 product
				break;
			}
		}
		/****************************************************/

		if( $apiResponse["bell"] == true ) {
			if( $prod_id_only == $prod_id_6  && $bell_plans_found == true ) {
				if( sizeof( $bell_plans_avail ) > 1 )  {
					continue; // skip the Internet 6 plan DSL
				}
			}
			if( $prod_id_only == $prod_id_15_1 ) {
				$found_marching15 = false;
				for($i=0; $i < sizeof($bell_plans_avail); $i++) {
					$pblan = trim($bell_plans_avail[$i]);
					if( strcmp( $pblan, "GASR01510N") == 0 ) {
						$found_marching15 = true;
						break;
					}
				}
				if( $found_marching15 == true) {
					continue;
				}
			}
		}

		$found_match = false;
		//* repeated above the explode sku, can be optimized, but playing safe for now
		$prod_sku = $prod->get_sku();
		$skus = explode(",",$prod_sku);
		foreach( $skus as $s ) {

			$s = trim($s);
			if( strlen($s) == 0 ) {
				continue;
			}

			if( $apiResponse["bell"] == true ) {
				for($i=0; $i < sizeof($bell_plans_avail); $i++) { 
					$pblan = trim($bell_plans_avail[$i]);
					$sku_bell_speed = $s;
					if( strlen( $pblan ) <= 0 || strlen($sku_bell_speed) <= 0 ) {
						continue;
					}
					if( strcmp( $pblan, $sku_bell_speed ) == 0 ) {
						$found_match = true;
						$bell_plans_found = true;
						break;
					}
				}
			}

			if( $apiResponse["telus"] == true ) 
			{
				for($i=0; $i < sizeof($telus_plans_avail); $i++) 
				{ 
					$pblan = trim( $telus_plans_avail[$i] );
					if( strcmp( $pblan, $s ) == 0 ) 
					{
						$found_match = true;
						break; // good continue;
					}
				}
			}

			if( $apiResponse["cogeco"] == true ) {
				if( strcmp( $s, "cogeco") == 0 ) {
					$found_match = true;
					break;  // good continue;
				}
			}

			if( $apiResponse["shaw"] == true ) {
				if( strcmp( $s, "shaw") == 0 ) {
					$found_match = true;
					break;  // good continue;
				}
			}

			if( $apiResponse["rogers"] == true ) {
				if( strcmp( $s, "rogers") == 0 ) {
					$found_match = true;
					break;  // good continue;
				}
			}
		}

		if( $found_match == false ) {
			continue; // do not add this product because its not available in the address
		}
		

		$show_ccd = trim( $prod->get_attribute("CCD_show") );
		$hide_ccd = trim( $prod->get_attribute("CCD_hide") );
		error_log(" ppget_internet_plans ---- $ccd_clear, show $show_ccd, hide $hide_ccd ");

		if( strlen( $show_ccd ) > 0 ) {
			$show_prod = false;
			if( strlen( $ccd_clear ) > 0 ) {
				$show_ccds = explode( ",", $show_ccd );
				foreach( $show_ccds as $s ) {
					$s = strtolower( trim( $s ) );
					if( strcmp($ccd_clear , $s ) == 0 ) {
						$show_prod = true;
						break;
					}
				}
			}
			if( $show_prod == false ) {
				continue;
			}
		}
		if( strlen( $ccd_clear ) > 0 &&  strlen( $hide_ccd ) > 0 ) {
			$show_prod = true;
			$hide_ccds = explode( ",", $hide_ccd );
			foreach( $hide_ccds as $s ) {
				$s = strtolower( trim( $s ) );
				if( strcmp($ccd_clear , $s ) == 0 ) {
					$show_prod = false;
					break;
				}
			}
			if( $show_prod == false ) {
				continue;
			}
		}

		$disply_prod .= "<div class=\"radio_box_wrapper\">";

		$prod_id = "Internet-" . $prod->get_id();

		error_log(" In render Internet plan, $prod_id_only " );

		$disply_prod .= '<input type="radio" name="InternetPlan" ';
		$disply_prod .= "id=\"$prod_id\" value=\"$prod_id_only\" ";

		if( strcmp( $selected_internet_plan, $prod_id_only ) == 0 ) {
			$disply_prod .= ' checked >';
		} else {
			$disply_prod .= '>';
		}


		if( $MainWebPage == 1 ) {
			$button_text = '<a class="btn_orange_grd plan_buy_now_btn" href="/residential/signup/?ccde=' . $ccd . '&add-plan={prod_id}" data-plan-id="{prod_id}" >Select Plan</a>';
		} else {
			$button_text = '<button type="button" class="button small confirmation_button confirm_internet_plan" onclick="select_internet_plan(\'' . $prod->get_id() . '\');"> <span aria-hidden="true">Confirm</span></button>'; 
		}
			//$button_text = ''; 

		$prod_desc = str_replace( "<div class=\"checkout_buttons\"></div>",
			str_replace("{prod_id}",$prod->get_id(),$button_text),$prod->get_description());

		$disply_prod .= "<label for=\"$prod_id\">";
		$disply_prod .= "<span class=\"internet-sku\" style=\"display:none\">$prod_sku</span>";
		$disply_prod .= $prod_desc;
		$disply_prod .= '</label>';

		$disply_prod .= "</div>";
		$found_one_match = true;
	}

	$disply_prod .= "</div>";

	if( $found_one_match == false ) {
		return $ret_invald_address;
	}

	return $disply_prod;
}

add_action( 'wp_enqueue_scripts', 'diallog_theme_enqueue_styles',99);
function diallog_theme_enqueue_styles() {
	  //wp_enqueue_style( 'font-awesome', '//stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' );
	  wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
	  wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array('parent-style'),"1.5.0.7","all");
	  wp_enqueue_style( 'custom-style', get_stylesheet_directory_uri() . '/css/custom.css', array('parent-style','child-style'),"0.9.1.6","all");
	  wp_dequeue_style('divi-style');
	  wp_enqueue_style( 'multisteps-style', get_stylesheet_directory_uri() . "/css/multistep.css", array() , "0.9.1","all");
	  
	  wp_enqueue_script( "bootstrap-main", get_stylesheet_directory_uri(). "/js/bootstrap.min.js" , array("jquery"), "3.3.7","all");
	  
	  wp_enqueue_script( "jquery-mask", get_stylesheet_directory_uri() . '/js/jquery.mask.min.js', array("jquery"), "1.14.16", true );

	  wp_enqueue_script( "diallog-main", get_stylesheet_directory_uri() . '/js/d-main.js', array("jquery"), "0.0.7", true );
	
	  //wp_enqueue_script( "google-maps-api2","//maps.googleapis.com/maps/api/js?key=AIzaSyCVLq3DrRD2BizXm-yZ-WsD2qq0ofWN2VU&libraries=places" , array(), "1.0", true );
	  wp_enqueue_script( "google-maps-api2","//maps.googleapis.com/maps/api/js?key=AIzaSyAQ_uaqGJF-ALsSrkYEzKOHbI27WC-vEZg&libraries=places" , array(), "1.0", true );
	  //wp_enqueue_script( "google-maps-api2","https://maps.googleapis.com/maps/api/js?key=AIzaSyB1IAKntR9qg34Q-4eANCMqNoNQ1UE8j1M&libraries=places" , array(), "1.0", true );
	  wp_enqueue_script("jquery-ui","https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js",array("jquery"),"1.12.1",false);
	  wp_enqueue_script("jquery-ui-datepicker");
	  wp_enqueue_style('jquery-ui','https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/south-street/jquery-ui.css',false,'1.12.1',false);
}

add_action("wp_head","dg_wp_head",99);
function dg_wp_head() {
	
	if (is_page(2935) && sizeof( WC()->cart->get_cart() ) == 0 ) {
		
		wp_safe_redirect(get_the_permalink(2265)."?show_availability_checker");
		exit;
	}
	
	
	echo "<script> var dg_today = new Date('".date("m/d/Y")."'); var default_days = 6; </script>"."\r\n";
	//echo "<script> var dg_today = new Date('2019-04-16'); var default_days = 6; </script>"."\r\n";
	
	$invalid_service_dates['ON'] = array("01/01/2019","02/18/2019","04/19/2019","05/12/2019","05/20/2019","06/16/2019","07/01/2019","08/05/2019","09/02/2019","10/14/2019","12/25/2019","12/26/2019");
	
	$invalid_service_dates['QC'] = array("01/01/2019","04/19/2019","04/22/2019","05/12/2019","05/20/2019","06/16/2019","06/24/2019","07/01/2019","09/02/2019","10/14/2019","12/25/2019","12/31/2019");
	
	
	echo "<script>";
		
		echo "var invalid_service_dates = ["."\r\n";	
	
	$searched_address =  dg_get_user_meta ("searched_address");	
	
	if (!empty($searched_address['provinceOrState'])) {
		
		$st = $searched_address['provinceOrState'];
		
	} else {
		
		$st = "ON";
		
	}
	
	if (is_array($invalid_service_dates[$st]) && count($invalid_service_dates[$st])>0) {
		foreach ($invalid_service_dates[$st] as $isd) {
			echo "'$isd',"."\r\n";
		}
	} 
	echo "]"."\r\n";
	echo "</script>";
	
}

add_shortcode("dg_get_internet_plans","dg_get_internet_plans");
function dg_get_internet_plans( $atts ) {

	$atts = shortcode_atts( array(
		'ids' => 'all',
		'type'=> array('internet-plan'),
	), $atts, 'dg_get_internet_plans' );
	
	if ($atts['ids']=="all") {
		
		$query = new WC_Product_Query( array(
		    'limit' => -1,
		    'orderby' => 'date',
		    'order' => 'DESC',
		    'category' => $atts['type'],
		    
		) );
	
	} else {
		
		$query = new WC_Product_Query( array(
		    'limit' => 1,
		    'orderby' => 'date',
		    'order' => 'DESC',
		    'category' => $atts['type'],
		    'include' => explode(",",$ids)
		    
		));

	}
	
	$products = $query->get_products();
	//var_dump($products);
	$button_text = '<a class="btn_mute plan_check_availability_btn" href="#" data-target="#plan-building-wizard-modal" data-toggle="modal" rel="noopener noreferrer">Check Service</a> 
                    <a style="display:none;" class="btn_orange_grd plan_buy_now_btn" href="/residential/signup/?add-plan={prod_id}" data-plan-id="{prod_id}" >Select Plan</a>';

	$disply_prod = "";	
	foreach ($products as $prod) {
		
		if($prod->is_in_stock() == false) {
			continue;
		}

		$prod_desc = str_replace( "<div class=\"checkout_buttons\"></div>",
			str_replace("{prod_id}",$prod->get_id(),$button_text),$prod->get_description()); 
		
		$skus = explode(",",$prod->get_sku());
		
		$prod_class = "offer-".implode(" offer-",$skus);
		
		$disply_prod .= "<div class='internet-offer $prod_class' >". $prod_desc ."</div>";  	
		
	}
	
	return $disply_prod;

}

add_shortcode("dg_signup_page","dg_signup_page");
function dg_signup_page( $atts ) {

//echo ("In dg_signup_page " . time() . "<br>"); //Eugene
	
	ob_start();
	
	if (!is_admin()) {
		get_template_part("templates/page","signup");	
	}
	
	return ob_get_clean();	
	
}

function _add_product_tocart($plan_id, $type) {

	global $dg_order;
	global $woocommerce;

	error_log("In _add_product_tocart $plan_id");

	$product_id   = apply_filters('woocommerce_add_to_cart_product_id', absint( $plan_id ));
	$product_data = wc_get_product( $product_id );
	$product_cat_ids = $product_data->get_category_ids();
	$product_cat = get_term($product_cat_ids[0],'product_cat');
	$product_category = $product_cat->slug;
		
	$quantity = apply_filters( 'woocommerce_add_to_cart_quantity', 1, $product_id );

	if ( $quantity <= 0 || ! $product_data || 'trash' === $product_data->get_status() ) {
		return "Plan Not Available";        
	}

	$found = false;

	error_log("In _add_product_tocart - found plan id $product_id " );

	//check if product already in cart
	if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {
		foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {
			$_product = $values['data'];
			if ( $_product->id == $product_id ) {
				$found = true;
				break;
			}
		}
	}

	if (!$found) {
		
		//find a product in same category and remove it before adding new.
		foreach ( WC()->cart->get_cart() as $item_key => $value ) {
			$cart_product_data = wc_get_product( $value['product_id'] );							           
			$cart_product_cat_ids = $cart_product_data->get_category_ids();

			//if product is of same category then remove it; 
			if ($cart_product_cat_ids[0]==$product_cat_ids[0]) {  
				WC()->cart->remove_cart_item($item_key);
				break;
			}
		}
				
		error_log("In _add_product_tocart - adding it now $product_id, $quantity " );
		$ret = $woocommerce->cart->add_to_cart( $product_id, $quantity );
		error_log("In _add_product_tocart - adding it now ret = $ret " );

		dg_set_user_meta("selected_".$type."_plan",$product_id);
		dg_set_user_meta("selected_".$type."_plan_name",$product_data->get_title());

		error_log(" Cart_item_id $product_id, Cart_ItemCategory $product_category, Cart_ItemTitle " . $product_data->get_title() );
		
        dg_set_user_meta("cart_category_" . $product_id , $product_category);
		dg_set_user_meta("cart_title_" . $product_id , $product_data->get_title());
		dg_set_user_meta("category_" . $product_category, $product_id);

		if( strcmp( $product_category, "internet-plan" ) == 0 ) {
			$id = $product_data->get_attribute("id");
			dg_set_user_meta("category_" . $product_category, $id );
		}
		if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {

			SendInfoToSignupServer(2);

			return "success";

		} else {

			return "plan could not be added";

		}

	} else {
		return "success";
	}
}

add_action("wp_ajax_add_plan","dg_add_product_to_cart");	
add_action("wp_ajax_nopriv_add_plan","dg_add_product_to_cart");
function dg_add_product_to_cart ($plan_id = false , $type =  false) {

	$ret = "";

	if ($plan_id) {

		$ajax = false;
		$type = $type ? $type : "internet";

	} elseif (isset($_POST['plan_id']) && !empty($_POST['plan_id'])) {

		$ajax = true;
		$plan_id = sanitize_text_field($_POST['plan_id']);
		$type = sanitize_text_field( ($_POST['type']!="" ? $_POST['type'] : "internet") );

	}

	error_log( " 1- In dg_add_product_to_cart plan $plan_id, type $type " );

	// Add Installation Fees
	// 1. Check which Internet plan is selected
	// 2. Find the Fixed Fee, with SKU matching the sku of the internet plan
	// 3. Add it to cart
	if( $type == "Internet Plan" ) {
		// Check which plan is selected 
		// find the Fixed Fees with matching sku
		$internet_plan_id = wc_get_product( $plan_id );
		if( $internet_plan_id == false ) {
			goto error;
		}

		// add the internet plan
		$ret = _add_product_tocart($plan_id, $type);
		if( $ret != "success") {
			error_log("Added Internet Plan, $plan_id, but ret failed $ret");
			goto finish;
		}

	error_log( " 2- In dg_add_product_to_cart plan $plan_id, type $type " );

		// add the installation fees
		$internet_plan_sku = $internet_plan_id->get_sku();
		$internet_plan_sku_list = explode(",",$internet_plan_sku);

		$query = new WC_Product_Query( array(
			'limit' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
			'category' => "Fixed Fee",
		) );

	    error_log( " 3- In dg_add_product_to_cart plan $plan_id, type $type " );

		$products = $query->get_products();
		foreach($products as $prod) {
			$install_plan_sku = $prod->get_sku();
			$install_plan_sku_list = explode(",",$install_plan_sku);

            error_log( "In dg_add_product_to_cart in the product loop, install plan sku = $install_plan_sku, $plan_id, type $type " );

			foreach($install_plan_sku_list as $install_sku) {
				//foreach($internet_plan_sku_list as $internet_sku) {
					if( strcmp( $internet_plan_sku_list[0], $install_sku) == 0 ) {
						// found match;
						$plan_id = $prod->get_id();
						$ret = _add_product_tocart($plan_id, "fixedfee");
						goto finish;
					}
				//}
			}


		}

	    error_log( " 4- In dg_add_product_to_cart plan $plan_id, type $type " );

	}

	if ($plan_id) {
		//hubspot_set_user_data();
		$ret = _add_product_tocart($plan_id, $type);
		error_log(" dg_add_product_to_cart return $ret ") ;
	} 
	    

finish:

    error_log( " 5- In dg_add_product_to_cart plan $plan_id, type $type " );

	if ($ajax) {
		die($ret);		
	} else {
		return false;
	}

error:
	if ($ajax) {
		die("Error");		
	} else {
		return false;
	}
}


add_action("wp_ajax_remove_from_cart","dg_remove_from_cart");	
add_action("wp_ajax_nopriv_remove_from_cart","dg_remove_from_cart");
function dg_remove_from_cart ( $type = false , $category = false ) {
	
	global $woocommerce;
	
	if ($type && $category) {
		
		$ajax = false;
		
	
	} elseif ( isset($_POST['action']) && $_POST['action']=="remove_from_cart" ) {
		$ajax = true;

		error_log("Action = remove_from_cart" );
		if( isset( $_POST['plan_id'] ) == true ) {
			$plan_id = $_POST['plan_id'];
			error_log("Action = remove_from_cart $plan_id" );
			foreach ( WC()->cart->get_cart() as $item_key => $value ) {
				if( $value['product_id'] == $plan_id ) {
					WC()->cart->remove_cart_item($item_key);
				}	
			}
			die("success");		
		}

		$type = sanitize_text_field($_POST['type']);
		$category = sanitize_text_field($_POST['category']);
	} 

	if( strlen($type) > 0 && strcmp($type, "all-types") == 0 ) {
		WC()->cart->empty_cart();
		if ($ajax) {
			die("success");		
		} else {
			return true;
		}
	}

	if ($type && $category) {
		foreach ( WC()->cart->get_cart() as $item_key => $value ) {

			$cart_product_data = wc_get_product( $value['product_id'] );							           
			$cart_product_cat_ids = $cart_product_data->get_category_ids();
			$product_cat = get_term($cart_product_cat_ids[0],'product_cat');
			$product_category = $product_cat->slug;

			//if product is of same category then remove it; 
			if ($product_category==$category) {

				WC()->cart->remove_cart_item($item_key);
				dg_set_user_meta("selected_".$type."_plan","");
				dg_set_user_meta("selected_".$type."_plan_name","");

				if ($type=="internet") {
					dg_set_user_meta("order_step", 1);		
				} elseif ($type=="phone") {
					dg_set_user_meta("order_step",5);		
				} elseif ($type=="modems") {
					dg_set_user_meta("order_step",6);	
				}

				if ($ajax) {
					die("success");		
				} else {
					return true;
				}

				break;
			}
		}
	} else {
		if ($ajax) {
			die("error");		
		} else {
			return false;
		}
	}
    
	if ($ajax) {
		die();		
	} else {
		return false;
	}
}


add_action("wp_ajax_update_order_review","dg_update_order_review");	
add_action("wp_ajax_nopriv_update_order_review","dg_update_order_review");
function dg_update_order_review () {
	
	global $woocommerce;
	
	do_action( 'woocommerce_checkout_order_review' );
	
	die();

    
}

add_action("wp_ajax_get_order_summary_page","dg_get_order_summary_page");	
add_action("wp_ajax_nopriv_get_order_summary_page","dg_get_order_summary_page");
function dg_get_order_summary_page () {
		
	get_template_part("templates/page","signup-review-order");

	die();
    
}


add_action("wp_ajax_update_user_data","dg_update_user_data");	
add_action("wp_ajax_nopriv_update_user_data","dg_update_user_data");
function dg_update_user_data () {
	
	global $woocommerce;

	$check_credit_card = false;
	$card_number = "";
	$expdate = "";
	$postal_code = "";
	$cvd = "";
	
	$state = 3;

	if (isset($_POST['data']) && !empty($_POST['data'])) {
		
		foreach ($_POST['data'] as $key=>$value) {
			dg_set_user_meta(sanitize_text_field($key),sanitize_text_field($value));

			if( strcmp( $key , "order_step" ) == 0 ) {
				$state = trim($value);
				error_log("-------State = $state" );
			}

			if( strcmp( $key, "monthly_bill_payment_option" ) == 0 ) {
				$monthly_bill_payment_option = strtolower( trim($value) );
				if( strcmp( $monthly_bill_payment_option, "cc" ) == 0 ) {
					$check_credit_card = true;
				}
			}
			if( strcmp( $key, "cc_monthly_billing_card_number" ) == 0 ) {
				$card_number = Cleanup_Number( base64_decode( $value ) );
				error_log( "Monthly Card number $card_number " );
			}
			if( strcmp( $key, "cc_monthly_billing_card_expiry" ) == 0 ) {
				$expdate = Cleanup_Number( $value );
				$expdate = substr($expdate, 2) . substr( $expdate, 0, 2);
				error_log( "Monthly Card expre $expdate " );
			}
			if( strcmp( $key, "cc_monthly_billing_postcode" ) == 0 ) {
				$postal_code= strtolower(trim ($value) );
			}
			if( strcmp( $key, "cc_monthly_billing_card_cvv" ) == 0 ) {
				$cvd = strtolower(trim ($value) );
			}
		}

		if( $check_credit_card == true ) {

			error_log("Sending verification information: $cust_id, $amount, $card_number, $expdate, $cvd, $postal_code");

			$payment_info['custid'] = "";
			$payment_info['orderid'] = 'verify-'.date("dmy-G:i:s");
			$payment_info['amount'] = 0.01; 
			$payment_info['cardno'] = $card_number;
			$payment_info['expdate'] = $expdate; //'2011';
			$payment_info['postal_code'] = $postal_code;
			$payment_info['cvd'] = $cvd;

			verify_card_ex( $payment_info );
		}

		$user_data = dg_get_current_user_data();
		//if( strcmp( $user_data.lead_status, "Need manual check" ) == 0 ) {
		//	SendInfoToSignupServer(0);
		//} else {
			SendInfoToSignupServer($state); 
		//}

		$response["status"] = "success";
		$res = json_encode( $response );
		die( $res );
			
	} else {
	
		die("Error");
	
	}
	
	die();
}

add_action( 'init', 'all_set_cookies_tasks' );
function all_set_cookies_tasks() {

	if (isset($_GET['src']) && $_GET['src']=="pbw") {
		setcookie("plan_building_complete", true, strtotime( '+14 days' ) ,"/");	
	}

}

	
/* Register Custom Post */
/* ----------------------------------------------------- */
add_action( 'init', 'wdg_create_post_type' );
function wdg_create_post_type() {  // clothes custom post type
    // set up labels
    $labels = array(
        'name' => 'Careers',
        'singular_name' => 'Career Item',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Career Item',
        'edit_item' => 'Edit Career Item',
        'new_item' => 'New Career Item',
        'all_items' => 'All Career',
        'view_item' => 'View Career Items',
        'search_items' => 'Search Career',
        'not_found' =>  'No Careers Found',
        'not_found_in_trash' => 'No Careers found in Trash',
        'parent_item_colon' => '',
        'menu_name' => 'Careers',
    );
    register_post_type(
        'careers',
        array(
            'labels' => $labels,
            'has_archive' => true,
            'public' => true,
            'hierarchical' => true,
            'supports' => array( 'title', 'editor', 'excerpt', 'custom-fields', 'thumbnail' ),
            'exclude_from_search' => true,
            'capability_type' => 'post',
        )
    );
    // set up labels
    $labels_feedback = array(
        'name' => 'Testimonials',
        'singular_name' => 'Testimonial Item',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Testimonial Item',
        'edit_item' => 'Edit Testimonial Item',
        'new_item' => 'New Testimonial Item',
        'all_items' => 'All Testimonial',
        'view_item' => 'View Testimonial Items',
        'search_items' => 'Search Testimonial',
        'not_found' =>  'No Testimonial Found',
        'not_found_in_trash' => 'No Testimonial found in Trash',
        'parent_item_colon' => '',
        'menu_name' => 'Testimonial',
    );
    register_post_type(
        'testimonial',
        array(
            'labels' => $labels_feedback,
            'has_archive' => true,
            'public' => true,
            'hierarchical' => true,
            'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
            'exclude_from_search' => true,
            'capability_type' => 'post',
        )
    );
}
 
// register two taxonomies to go with the post type
add_action( 'init', 'wdg_create_taxonomies', 0 );
function wdg_create_taxonomies() {
    // color taxonomy
    $labels = array(
        'name'              => _x( 'Jobs', 'taxonomy general name' ),
        'singular_name'     => _x( 'Job', 'taxonomy singular name' ),
        'search_items'      => __( 'Search Jobs' ),
        'all_items'         => __( 'All Jobs' ),
        'parent_item'       => __( 'Parent Job' ),
        'parent_item_colon' => __( 'Parent Job:' ),
        'edit_item'         => __( 'Edit Job' ),
        'update_item'       => __( 'Update Job' ),
        'add_new_item'      => __( 'Add New Job' ),
        'new_item_name'     => __( 'New Job' ),
        'menu_name'         => __( 'Jobs' ),
    );
    register_taxonomy(
        'jobs',
        'careers',
        array(
            'hierarchical' => true,
            'labels' => $labels,
            'query_var' => true,
            'rewrite' => true,
            'show_admin_column' => true
        )
    );
}


// create shortcode with parameters so that the user can define what's queried - default is to list all blog posts
add_shortcode( 'careersposts', 'wdg_careers_shortcode' );
function wdg_careers_shortcode( $atts ) {
    ob_start();
    // define attributes and their defaults
    extract( shortcode_atts( array (
        'type' => 'careers',
        'order' => 'date',
        'orderby' => 'title',
        'posts' => -1,
        'jobs' => ''
    ), $atts ) );
    // define query parameters based on attributes
    $options = array(
        'post_type' => $type,
        'order' => $order,
        'orderby' => $orderby,
        'posts_per_page' => $posts,
        'jobs' => $jobs
    );
    $string = '';
    $query = new WP_Query( $options );
    if( $query->have_posts() ){
        $string .= '<div class="career_main_area">';
        while( $query->have_posts() ){
            $query->the_post();
            $ttt = get_the_term_list( $post->ID, 'jobs', '', ', ' );
            $string .= '<div class="career_box_inner">';
            $string .= '<h4 class="career_box_category">' .  $ttt . '</h4>';
            $string .= '<div class="career_box_detail">';
            $string .= '<h2 class="carrer_box_title">'. get_the_title() .'</h2>';
            $string .= ' '. get_the_excerpt() .' ';
            $string .= '<a class="green-shade" href="'. get_the_permalink() .'">VIEW DETAIL</a>';
            $string .= '</div>';
            $string .= '</div>';
        }
        $string .= '</div>';
    }
    wp_reset_postdata();
    return $string;
}

add_filter( 'upload_mimes', 'my_myme_types', 99, 1 );
function my_myme_types( $mime_types ) {
  $mime_types['otf'] = 'font/otf';    
  $mime_types['ttf'] = 'font/ttf';
  
  return $mime_types;
}


add_shortcode( 'multi_form', 'wdg_multistep_form' );
function wdg_multistep_form( $atts ) {    
 	
 	// Attributes
    extract( shortcode_atts( array (
        'value' => 'Bring Your Friend'
    ), $atts ) ); 
    
    $return = "";
    
    if ($value != ' ') { 
    
    	$return = '<button type="button" class="green-shade btn-fix" data-toggle="modal" data-target=".bs-example-modal-lg"><?php echo $value; ?></button>';
    
    };
 
    return $return;
}


/* All Functions for plan building wizard */

function plan_building_wizard_modal() {
   
   
   include_once("plan-building-wizard-modal.php");
   include_once("basic-dialog-modal.php");
   include_once("abandon-cart-popup-modal.php");
   
   //WC()->cart->empty_cart();
   //var_dump( WC()->cart);
   
   
   
}

function add_phone_to_cart() {
	
	if (is_page(2265) && isset($_GET['addphn']) && !empty($_GET['addphn'])) {
		
		$the_slug = sanitize_text_field($_GET['addphn']);
		$args = array(
		  'name'        => $the_slug,
		  'post_type'   => 'product',
		  'post_status' => 'publish',
		  'numberposts' => 1
		);
		$my_posts = get_posts($args);
		
		if ($my_posts) {
			$pro_id =  $my_posts[0]->ID;	
			dg_add_product_to_cart ($pro_id , "phone");
		}
		
		wp_safe_redirect(get_the_permalink(2265));
		exit;
		
	}
	
}

function update_menu_cart_icon() {
	
	
	$onboarding_stage = dg_get_user_meta ("onboarding_stage");
	
	//not signup and checkout page
	if (!is_page(2935) && !is_page(2867) && !is_page(2265) && sizeof( WC()->cart->get_cart() ) > 0 ) {
		
		if ($onboarding_stage=="signup_initiated") {
			
			$display = "inline-block";
			$modal   = "show";
			$title = "Signup and Checkout";
			$color = "green";
			$link = "/residential/signup/";
			$text = "You have items left in your cart.";
			$btn_text = "Complete the signup";
		
		} elseif ($onboarding_stage=="signup_complete" || $onboarding_stage=="checkout_initiated" ) {
			
			$display = "inline-block";
			$modal   = "show";
			$title = "Complete your pending order";
			$color = "orange";
			$link = "/residential/checkout/";
			$text = "You are just a few steps away from completing your order!";
			$btn_text = "Checkout";
		
		} else {
			
			$display = "none";
			$modal   = "hide";
			$title = "";
			$color = "";
			$link = "#";
			$text = "";
			$btn_text = "";
			
					
		}
		
	} else {
		
		$display = "none";
		$modal   = "hide";
		$title = "";
		$color = "";
		$link = "#";
		$text = "";
		$btn_text = "";
		
	} ?>
	
	
	<script>
		
		jQuery(document).ready(function(){
			
			setTimeout(function(){ show_checkout_popup() },5000);
			
			
			jQuery(".et-cart-info").css("display","<?=$display?>");
		    jQuery(".et-cart-info").css("marginTop","10px");
		    jQuery(".et-cart-info").css("marginBottom","10px");
			jQuery(".et-cart-info").css("color","<?=$color?>");
			jQuery(".et-cart-info span").html("<?=$title?>&raquo;");
			jQuery(".et-cart-info").attr("href","<?=$link?>");
		    	
		
		});	
		
		function show_checkout_popup() {
		
			
			if (dg_getCookie('close_checkout_popup')) {
				
				return;
				
			}
	
			jQuery("#abandon-cart-popup-modal .quiz-title h3").html('<?=$title?>');
		    jQuery("#abandon-cart-popup-modal .modal-body .textsg").html('<?=$text?>');
		    jQuery("#abandon-cart-popup-modal #nextBtn span").html('<?=$btn_text?>');
		    jQuery("#abandon-cart-popup-modal #nextBtn").unbind("click").click(function(e){
		    	
		    	e.preventDefault;
		    	window.location.href= '<?=home_url($link)?>';
		    	
		    
		    });
		    
		    jQuery("#abandon-cart-popup-modal .close").unbind("click").click(function(e){
		    	
		    	//dg_setCookie("close_checkout_popup",1,1);	
		    
		    });
		    
		    jQuery("#abandon-cart-popup-modal").modal("<?=$modal?>");
		    
		    /*jQuery("#abandon-cart-popup-modal").on("hide.bs.modal",function(e){
		
			    dg_setCookie("close_checkout_popup",1,1);	
				
			});
		    */
		    
	    
	    }
				
	</script>
	
	
	<?php
	
	
}

function dg_show_availability_checker() {
	
	if (isset($_GET['show_availability_checker'])) { ?>
		
		<script>
			jQuery(document).ready(function(){
				jQuery("#plan-building-wizard-modal").modal("show");
			});
		</script>
		
	<?php }
}


function save_utm_parameters() {
	
	if (!empty($_GET['utm_source'])) {
		dg_set_user_meta ("utm_source",sanitize_text_field($_GET['utm_source']));	
	}
	if (!empty($_GET['utm_medium'])) {
		dg_set_user_meta ("utm_medium",sanitize_text_field($_GET['utm_medium']));
	}
	if (!empty($_GET['utm_campaign'])) {
		dg_set_user_meta ("utm_campaign",sanitize_text_field($_GET['utm_campaign']));
	}
	if (!empty($_GET['utm_term'])) {
		dg_set_user_meta ("utm_term",sanitize_text_field($_GET['utm_term']));
	}
	if (!empty($_GET['utm_content'])) {
		dg_set_user_meta ("utm_content",sanitize_text_field($_GET['utm_content']));
	}
	
}

function toc_modals_html() {
	
	//if is page checkout
	if (is_page(2867)) { 
		
		get_template_part("templates/page","checkout-toc-modals");
		 
	}
}

function load_phone_rates() {
	
	//if phone page then load rates
	if (is_page(251)) { ?>
	
	<script>
		load_phone_rates();
	</script>	
		
	<?php }
	
}


add_action( 'wp_footer', 'plan_building_wizard_modal',1);
//add_action( 'wp_footer', 'reload_bellapi_response',11);
add_action( 'wp_footer', 'add_phone_to_cart',12);
add_action( 'wp_footer', 'update_menu_cart_icon',13);
add_action( 'wp_footer', 'dg_show_availability_checker',99);
add_action( 'wp_footer', 'save_utm_parameters',100);
add_action( 'wp_footer', 'toc_modals_html',100);
add_action( 'wp_footer', 'load_phone_rates',100);


add_action( 'wp_ajax_nopriv_get_ld_rates', 'get_ld_rates' );
add_action( 'wp_ajax_get_ld_rates', 'get_ld_rates' );
function get_ld_rates() {
	
	global $wpdb;
	
	$data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dg_ld_rates ORDER BY country_name ASC", ARRAY_A);
	$country = [];
	
	if ($data && is_array($data)) {
		
		
		$country[0]['rate'] = " ";
		$country[0]['name'] = " ";
		$country[0]['code'] = " ";
		
		$c = 1;

		foreach ($data as $row) {
			
			$country[$c]['rate'] = $row['rates'];
			$country[$c]['name'] = $row['country_name'];
			$country[$c]['code'] = $row['country_code'];
			
			$c++;				
		}
		 
	}
	
	wp_die(json_encode($country));
	
}



add_action( 'wp_ajax_nopriv_show_pbw', 'ajax_show_pbw' );
add_action( 'wp_ajax_show_pbw', 'ajax_show_pbw' );
function ajax_show_pbw() {
    include_once("plan-building-wizard-steps.php");
    wp_die();
}



// Show Payment Options after Billing fields. 
remove_action( 'woocommerce_checkout_order_review','woocommerce_checkout_payment',20);
add_action( 'woocommerce_checkout_before_customer_details','woocommerce_checkout_payment',10);			 

// Optimized by Eugene with help from ChatGPT
function get_dg_user_id() {
	global $dg_user_id;

	if (isset($_COOKIE['dg_user_hash'])) {
		$dg_user_id = $_COOKIE['dg_user_hash'];
	} elseif (!empty($dg_user_id)) {
		// already set
	} else {
		$dg_user_id = false;
	}

	return $dg_user_id;
}

function set_dg_user_id($id) {
	global $dg_user_id;
	$dg_user_id = $id;
	setcookie("dg_user_hash", $dg_user_id, strtotime('+30 days'), "/");
}

function dg_get_user_meta($key) {
	global $wpdb;

	$user_id = get_dg_user_id();
	if (!$user_id) return false;

    $main_key = "full_user_data";
	$data = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->prefix}dg_user_data WHERE user_id = %s AND meta_key = %s",
			$user_id, $main_key
		),
		ARRAY_A
	);

    if( $data )
    {
        $main_value_d = json_decode( base64_decode ( $data['meta_value'] ) , true );
        if( isset( $main_value_d[$key] ) && $main_value_d[$key] ) {
            return maybe_unserialize( base64_decode( $main_value_d[$key] ) );
        }
    }
    
	return false;//$data ? maybe_unserialize($data['meta_value']) : false;
}

function dg_set_user_meta($key, $value = "") {
	global $wpdb;

	$user_id = get_dg_user_id();
	if (!$user_id) {
		$user_id = md5(microtime(true) . rand());
		set_dg_user_id($user_id);
		dg_set_user_meta("date_created", date("j M,Y H:i:s"));
	}

    $existing = true;
    $user_data = dg_get_current_user_data(true);//dg_get_user_meta($key);
    if( $user_data == false )
        $existing = false;
    

    $value = base64_encode( maybe_serialize( $value ) );
    $main_key = "full_user_data";


	if ($existing !== false) {
        $main_value_d = dg_get_current_user_data(true);
        $main_value_d[$key] = $value;
        $main_value = base64_encode( json_encode( $main_value_d ));

		return $wpdb->update(
			$wpdb->prefix . "dg_user_data",
			['meta_value' => $main_value],
			['user_id' => $user_id, 'meta_key' => $main_key],
			['%s'], ['%s', '%s']
		);

	} else {
		$main_value_d = [];
        $main_value_d[$key] = $value;
        $main_value = base64_encode( json_encode( $main_value_d ));

		return $wpdb->insert(
			$wpdb->prefix . "dg_user_data",
			[
				'user_id'    => $user_id,
				'meta_key'   => $main_key,
				'meta_value' => $main_value,
			],
			['%s', '%s', '%s']
		);
	}
}

function dg_get_current_user_data($unserialized = false) {
	global $wpdb;

	$user_id = get_dg_user_id();
	if (!$user_id) return false;

    $main_key = "full_user_data";
	$data = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->prefix}dg_user_data WHERE user_id = %s AND meta_key = %s",
			$user_id, $main_key
		),
		ARRAY_A
	);

	if (!$data || isset( $data['meta_value']) == false ) return false;

    $user_data = json_decode( base64_decode( $data['meta_value'] ), true );
    if( $unserialized == false ) {
        $ret_data = [];

        foreach( $user_data as $key => $value ) { // decode all items
            $ret_data[ $key ] = maybe_unserialize ( base64_decode( $value ) );
        }

        return $ret_data;
    } else {
        return $user_data;
    }

	return false;    
}

function dg_set_current_user_data($user_data) {
	if (!is_array($user_data)) return;

    $user_id = get_dg_user_id();
	if (!$user_id) return false;

    $existing = true;
    $current_data = dg_get_current_user_data(true);
    if( $current_data == false ) {
        $existing = false;
        $current_data = [];
    }

    foreach ($user_data as $key => $value) {
        $current_data[$key] = base64_encode ( maybe_serialize( $value ));
	}

    $main_value = base64_encode( json_encode( $data ));
    $main_key = "full_user_data";

    if ($existing !== false) {
		return $wpdb->update(
			$wpdb->prefix . "dg_user_data",
			['meta_value' => $main_value],
			['user_id' => $user_id, 'meta_key' => $main_key],
			['%s'], ['%s', '%s']
		);
	} else {
		return $wpdb->insert(
			$wpdb->prefix . "dg_user_data",
			[
				'user_id'    => $user_id,
				'meta_key'   => $main_key,
				'meta_value' => $main_value,
			],
			['%s', '%s', '%s']
		);
	}

}
// End optimized by Eugene with help from ChatGPT

function GetTaxRate() {
	$user_data = dg_get_current_user_data();
	if( $user_data == false ) {
		return 13;
	}

	$prov = $user_data['prov'];
        $tax_rate = 13;
        if( strcasecmp( $prov, "BC") == 0 ) {
                $tax_rate = 12;
        } else if( strcasecmp( $prov, "QC") == 0 ) {
                $tax_rate = 14.975;
        }
	return $tax_rate;
}

function get_monthly_fee_summary() {

	$summary = array(
		'internet-plan'=>array('',0.0),
		'modems'=>array('',0.0),
		'phone-plan'=>array('',0.0),
		'subtotal'=>array('Subtotal',0.0),
		'taxes'=>array('Taxes',0.0),
		'grand_total'=>array('MONTHLY TOTAL',0.0));

	$show_included_taxes = wc_tax_enabled() && WC()->cart->display_prices_including_tax();
	$tax_rate = GetTaxRate();

	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

		$_product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		$product_cat_ids = $_product->get_category_ids();


		$product_cat = get_term($product_cat_ids[0],'product_cat');

		$product_category = $product_cat->slug;

		if ( array_key_exists($product_category, $summary) && $_product && 
		 		$_product->exists() && $cart_item['quantity'] > 0 && 
		 		apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {

			if( $product_category == "modems") {

				if( $_product->get_attribute("Security Deposit") > 0 ) {

					$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
					$summary[$product_category][1] = floatval($_product->get_price());
					$summary['taxes'][1] +=  round( floatval(( $_product->get_price() * $tax_rate ) / 100), 2 );  // floatval($_product->get_price_including_tax($cart_item['quantity'])-$_product->get_price());

				} else {

				}

			} else {
			
				if ($product_category!="modems" || ($product_category=="modems" && ($_product->get_id()!=887 && $_product->get_id()!=6931))) {
					$summary[$product_category][0] = $_product->get_title() . " " . ( $show_included_taxes ?"(inc taxes)":"")."" ;
					$summary[$product_category][1] = round( floatval($_product->get_price()), 2);
					//$summary['taxes'][1] += floatval($_product->get_price_including_tax($cart_item['quantity'])-$_product->get_price());
					$summary['taxes'][1] +=  round( floatval(( $_product->get_price() * $tax_rate ) / 100), 2 );  // floatval($_product->get_price_including_tax($cart_item['quantity'])-$_product->get_price());
					//$_product->get_price()*$cart_item['quantity'];	 
			 	}

			
/*
			if( $_product->get_id()==6931 ){
				$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
				//$summary[$product_category][1] =  floatval($line_data['subtotal']);
				$summary[$product_category][1] =  floatval(2);
				
				$summary['taxes'][1] += floatval($line_data['total_tax']);
			}

			if( $_product->get_id()==887 ){
				$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
				//$summary[$product_category][1] =  floatval($line_data['subtotal']);
				$summary[$product_category][1] =  floatval(0);
				
				$summary['taxes'][1] += floatval($line_data['total_tax']);
			}
*/
			}
		}
	}

	$summary['subtotal'][1] = $summary['internet-plan'][1] + $summary['modems'][1] + $summary['phone-plan'][1];

	if (wc_tax_enabled() && !$show_included_taxes ) {
		 $summary['taxes'][0] = esc_html( WC()->countries->tax_or_vat() );
		 $summary['grand_total'][1] = $summary['subtotal'][1] + $summary['taxes'][1];
	} elseif (!wc_tax_enabled()) {
		$summary['taxes'][0] = "Tax";
		$summary['taxes'][1] = 0.0;
		$summary['grand_total'][1] = $summary['subtotal'][1];
	}

	return $summary;	
}


function get_monthly_order_summary($order) {
	
	$summary = array(
		'internet-plan'=>array('',0.0),
		'modems'=>array('',0.0),
		'phone-plan'=>array('',0.0),
		'subtotal'=>array('Subtotal',0.0),
		'taxes'=>array('Taxes',0.0),
		'grand_total'=>array('MONTHLY TOTAL',0.0));
	
	$show_included_taxes = false;
	$tax_rate = GetTaxRate();

	foreach ($order->get_items() as $cart_item_key => $cart_item ) {
		 
		 $_product     = $cart_item->get_product();
		 $line_data    = $cart_item->get_data();
		
		 $product_cat_ids = $_product->get_category_ids();
		 $product_cat = get_term($product_cat_ids[0],'product_cat');
		 $product_category = $product_cat->slug;
		 
		 if (array_key_exists($product_category,$summary) && $_product && $_product->exists() && $line_data['quantity'] > 0) {
			 
			 if ($product_category!="modems" || ($product_category=="modems" && ($_product->get_id()!=887 && $_product->get_id()!=6931)) ) {
				$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
				$summary[$product_category][1] =  round( floatval($line_data['subtotal']), 2 );
				
				$summary['taxes'][1] += round( floatval( ($line_data['subtotal'] * $tax_rate) / 100 ), 2);  //floatval($line_data['total_tax']);
			 }
			 
			if( $_product->get_id()==6931 ){
				$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
				//$summary[$product_category][1] =  floatval($line_data['subtotal']);
				$summary[$product_category][1] =  round( floatval(2), 2 );
				
				//$summary['taxes'][1] += floatval($line_data['total_tax']);
				$summary['taxes'][1] += round( floatval( ($summary[$product_category][1] * $tax_rate) / 100 ), 2);  //floatval($line_data['total_tax']);
			}

			if( $_product->get_id()==887 ){
				$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
				//$summary[$product_category][1] =  floatval($line_data['subtotal']);
				$summary[$product_category][1] =  round ( floatval(0), 2 );
				
				//$summary['taxes'][1] += floatval($line_data['total_tax']);
				$summary['taxes'][1] += round( floatval( ($summary[$product_category][1] * $tax_rate) / 100 ), 2);  //floatval($line_data['total_tax']);
			}
			 
		 }
	}
	
	$summary['subtotal'][1] = $summary['internet-plan'][1] + $summary['modems'][1] + $summary['phone-plan'][1];
	if (wc_tax_enabled() && !$show_included_taxes ) {
		
		 $summary['taxes'][0] = esc_html( WC()->countries->tax_or_vat() );
		 $summary['grand_total'][1] = $summary['subtotal'][1] + $summary['taxes'][1];	
	
	} elseif (!wc_tax_enabled()) {
		
		$summary['taxes'][0] = "Tax";
		$summary['taxes'][1] = 0.0;
		$summary['grand_total'][1] = $summary['subtotal'][1];
		
	}
	
	return $summary;	
}

add_action( 'wp_ajax_nopriv_get_upfront_fee_json', 'get_upfront_fee_json' );
add_action( 'wp_ajax_get_upfront_fee_json', 'get_upfront_fee_json' );
function get_upfront_fee_json() {
	
	$summary = array ();

	$summary['upfront'] = get_upfront_fee_summary();
	$summary['monthly'] = get_monthly_fee_summary();

	$json_response = json_encode($summary);

	wp_die($json_response);
	
}

function get_upfront_fee_summary() {
	
	$summary = array(
		'ModemPurchaseOption'=>true,
		'internet-plan'=>array('',0.0),
		'modems'=>array('Modem Security Deposit',0.0),
		'fixed-fee'=>array('Installation Fee',0.0),
		'deposit'=>array('Pay-after Deposit',0.0),
		'subtotal'=>array('Subtotal',0.0),
		'taxes'=>array('Taxes',0.0),
		'grand_total'=>array('UPFRONT TOTAL',0.0));

	$tax_rate = GetTaxRate();
	$do_not_include_modem_deposit = false;
	$show_included_taxes = wc_tax_enabled() && WC()->cart->display_prices_including_tax();
	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		 
		$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		$product_cat_ids = $_product->get_category_ids();
		$product_cat = get_term($product_cat_ids[0],'product_cat');
		$product_category = $product_cat->slug;
		 
		if (array_key_exists($product_category,$summary) && $_product && $_product->exists() && 
			$cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {

			error_log( "...." . $product_category );
			//TODO: in an effort to remove the 1st month internet payment from the inital payment
			if( $product_category == "internet-plan" ) {
				$summary[$product_category][0] = $_product->get_title();
				$summary[$product_category][1] = 0.00; //Eugene explicitly exclude cost, show the plan name though
				continue;
			}

			if( $product_category == "deposit" ) {
				$summary[$product_category][0] = $_product->get_title();
				$summary[$product_category][1] =  round(floatval($_product->get_price()), 2);
				// no taxes here for category deposit
			} else {
			if( $product_category == "modems" && $_product->get_attribute("Security Deposit") > 0  ) {
				
					$security_deposit = $_product->get_attribute("Security Deposit");
					$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
					$summary[$product_category][1] =  round(floatval($security_deposit), 2);
					// no tax on security deposit
					$summary['ModemPurchaseOption'] = false;
					$do_not_include_modem_deposit = true;

			} else {
				
				$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
				$summary[$product_category][1] =  round(floatval($_product->get_price()), 2);
				$summary['taxes'][1] += round( floatval ( ($summary[$product_category][1] * $tax_rate ) / 100 ) , 2 );	
				
			}
			}
		}
	}

	if( $do_not_include_modem_deposit ) {
			$summary['subtotal'][1] = $summary['internet-plan'][1] + $summary['fixed-fee'][1];
			$summary['grand_total'][1] = $summary['subtotal'][1]+ $summary['deposit'][1] + $summary['taxes'][1] + $summary['modems'][1];	

			if (wc_tax_enabled() && !$show_included_taxes ) {
				$summary['taxes'][0] = esc_html( WC()->countries->tax_or_vat() );
			} elseif (!wc_tax_enabled()) {
				$summary['taxes'][0] = "Tax";
				$summary['grand_total'][1] = $summary['subtotal'][1] + $summary['deposit'][1] + $summary['modems'][1];
			}
	} else {
			$summary['subtotal'][1] = $summary['internet-plan'][1] + $summary['fixed-fee'][1] + $summary['modems'][1];
			$summary['grand_total'][1] = $summary['subtotal'][1]+ $summary['deposit'][1] + $summary['taxes'][1];
			if (wc_tax_enabled() && !$show_included_taxes ) {
				$summary['taxes'][0] = esc_html( WC()->countries->tax_or_vat() );
			} elseif (!wc_tax_enabled()) {
				$summary['taxes'][0] = "Tax";
				$summary['grand_total'][1] = $summary['subtotal'][1] + $summary['deposit'][1];
			}
	}

	return $summary;	
}

// this function is called from woocommerce-order system, require $order param
function get_upfront_order_summary($order) {
		
	$summary = array(
		'internet-plan'=>array('',0.0),
		'modems'=>array('',0.0),
		'fixed-fee'=>array('Installation Fee',0.0),
		'deposit'=>array('Pay-after Deposit',0.0),
		'subtotal'=>array('Subtotal',0.0),
		'taxes'=>array('Taxes',0.0),
		'grand_total'=>array('UPFRONT TOTAL',0.0));

	$show_included_taxes = false;
	$do_not_include_modem_deposit = false;

	foreach ( $order->get_items() as $cart_item_key => $cart_item ) {
		 
		$_product     = $cart_item->get_product();
		$line_data    = $cart_item->get_data();
		
		$product_cat_ids = $_product->get_category_ids();
		$product_cat = get_term($product_cat_ids[0],'product_cat');
		$product_category = $product_cat->slug;
		 
		if ( array_key_exists($product_category,$summary) && $_product && $_product->exists() && $line_data['quantity'] > 0 ) {

			//TODO: in an effort to remove the 1st month internet payment from the inital payment
			if( $product_category == "internet-plan" ) {
				$summary[$product_category][0] = $_product->get_title();
				$summary[$product_category][1] = 0.00; // Eugene explicitly exclude cost, show plan name
				continue;
			}

			if( $product_category == "deposit" ) {
				$summary[$product_category][0] = $_product->get_title();
				$summary[$product_category][1] =  round(floatval($_product->get_price()), 2);
				// no taxes here for category deposit
			} else {
			if ( $product_category=="modems" && $_product->get_attribute("Security Deposit") > 0  ) {

				$security_deposit = $_product->get_attribute("Security Deposit");
				$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
				$summary[$product_category][1] =  round( floatval($security_deposit), 2);
				$do_not_include_modem_deposit = true;

			} else {

			 	$summary[$product_category][0] = $_product->get_title()." ".( $show_included_taxes ?"(inc taxes)":"")."" ;
				$summary[$product_category][1] =  round( floatval($line_data['subtotal']), 2 );
				$summary['taxes'][1] += floatval($line_data['total_tax']);
			
			}
			}

		}
		 
	}

	if( $do_not_include_modem_deposit ) {

		$summary['subtotal'][1] = $summary['internet-plan'][1] + $summary['fixed-fee'][1] ;	
		if (wc_tax_enabled() && !$show_included_taxes ) {
			$summary['taxes'][0] = esc_html( WC()->countries->tax_or_vat() );
			$summary['grand_total'][1] = $summary['subtotal'][1]+ $summary['deposit'][1]  + $summary['taxes'][1]+$summary['modems'][1];	
		} elseif (!wc_tax_enabled()) {
			$summary['taxes'][0] = "Tax";
			$summary['taxes'][1] = 0.0;
			$summary['grand_total'][1] = $summary['subtotal'][1]+ $summary['deposit'][1]  +$summary['modems'][1];	
		}

	} else {

		$summary['subtotal'][1] = $summary['internet-plan'][1] + $summary['fixed-fee'][1] + $summary['modems'][1];	
		if (wc_tax_enabled() && !$show_included_taxes ) {
			$summary['taxes'][0] = esc_html( WC()->countries->tax_or_vat() );
			$summary['grand_total'][1] = $summary['subtotal'][1]+ $summary['deposit'][1]  + $summary['taxes'][1];	
		} elseif (!wc_tax_enabled()) {
			$summary['taxes'][0] = "Tax";
			$summary['taxes'][1] = 0.0;
			$summary['grand_total'][1] = $summary['subtotal'][1]+ $summary['deposit'][1];	
		}
	}

	
	return $summary;	
}

add_filter( 'woocommerce_calculated_total', 'change_calculated_total', 10, 2 );
function change_calculated_total( $total, $cart ) {
    
	$summary = get_upfront_fee_summary();
		
	return $summary['grand_total'][1];
	
	//return 1;
}

add_action( 'woocommerce_calculate_totals', 'add_custom_price', 10, 1);
function add_custom_price( $cart_object ) {

    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        return;

    if ( did_action( 'woocommerce_calculate_totals' ) >= 2 )
        return;
        
    $summary = get_upfront_fee_summary();    

    $cart_object->subtotal = $summary['subtotal'][1];
    $cart_object->tax_total = $summary['tax'][1];
     
}

function GetModemsInfo(&$plan_name, &$fees, &$class_name, &$upfront_info ) {
	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			
		$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		if( $_product == false || $_product->exists() == false || $cart_item['quantity'] <= 0 ||
			apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) == false ) {
			
				continue;
		}

		$product_cat_ids = $_product->get_category_ids();
		$product_cat = get_term($product_cat_ids[0],'product_cat');
		$product_category = $product_cat->slug;

		if ($product_category != 'modems') {
			continue;
		}
		
		$class_name = esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) );
		$plan_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;' . wc_get_formatted_cart_item_data( $cart_item );
		
		$ProdPrice = $_product->get_price();
		$fees = "$" . number_format($ProdPrice, 2) . "/mo";
	
		if( $_product->get_attribute( "Security Deposit" ) > 0 ) {
			$sec_depoist = $_product->get_attribute( "Security Deposit" );
			$upfront_info = "<p class=\"dg_ord_data display_modem150\"> " . 
				"<span class=\"plan_darta\">One-time upfront modem deposit</span> " . 
				"<span class=\"plan_pricing\"> $" . $sec_depoist . "</span>" . 
				"</p>";
		} else {
			$upfront_info = "";
		}

		return true;
	}

	return false;
}

//Eugene updated to correctly show the promo price and regular price plus Free 3 months on the Email and Thank You page summaries
function GetPhoneInfo(&$plan_name, &$fees, &$class_name) {
	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			
		$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		if( $_product == false || $_product->exists() == false || $cart_item['quantity'] <= 0 ||
			apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) == false ) {
				continue;
		}

		$product_cat_ids = $_product->get_category_ids();
		$product_cat = get_term($product_cat_ids[0],'product_cat');
		$product_category = $product_cat->slug;

		if ($product_category != 'phone-plan') {
			continue;
		}
		
		$class_name = esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) );
		$plan_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;' . wc_get_formatted_cart_item_data( $cart_item );

		$ProdQuant = $cart_item['quantity'];
		$regular = round($_product->get_regular_price() * $ProdQuant, 2);
		$sale = round($_product->get_sale_price() * $ProdQuant, 2);
		$freq = $_product->get_attribute("Payment Frequency");
		$fees = "";

		// Eugene updated to show proper pricing for Phone section of Email and Thank You summaries
		if ($regular > 0 && $sale == 0) {
			$fees = "<s>$$regular/mo</s> Free for 3 months";
		} elseif ($sale > 0 && $sale < $regular) {
			$fees = "<s>$$regular/mo</s> $$sale/mo";
		} else {
			$fees = "$" . round($_product->get_price() * $ProdQuant, 2) . "/mo";
		}

		if (strlen($freq) > 0 && strpos($fees, $freq) === false) {
			$fees .= " " . $freq;
		}

		return true;
	}

	return false;
}

function GetInternetPlanInfo( &$plan_name, &$fees, &$class_name  ) {


	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			
		$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		if( $_product == false || $_product->exists() == false || $cart_item['quantity'] <= 0 ||
			apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) == false ) {
			
				continue;
		}

		$product_cat_ids = $_product->get_category_ids();
		$product_cat = get_term($product_cat_ids[0],'product_cat');
		$product_category = $product_cat->slug;

		if ($product_category != 'internet-plan') {
				 
			continue;
				 
		} 
		
		$class_name = esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) );
		$plan_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;' . wc_get_formatted_cart_item_data( $cart_item );
		
		$ProdQuant = $cart_item['quantity'];

		if( $_product->get_sale_price() > 0 ) {
			$ProdPrice = $_product->get_price();
			$fees_after = round($ProdPrice * $ProdQuant, 2);
			$fees_before = round($_product->get_regular_price() * $ProdQuant, 2);
			$fees = "<s>" . $fees_before . "</s> " . $fees_after;
		} else {
			$ProdPrice = $_product->get_price();
			$fees = round($ProdPrice * $ProdQuant, 2);
		}


		$freq = $_product->get_attribute("Payment Frequency");
		if( strlen($freq) > 0) {
			$fees .= " " . $freq;
		}

		return true;
	}

	return false;
}

function GetInstallationFees(&$fees, &$class_name, &$name) {

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			if( $_product == false || $_product->exists() == false || $cart_item['quantity'] <= 0 ||
				apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) == false ) {
				
					continue;
			}

			$product_cat_ids = $_product->get_category_ids();
			$product_cat = get_term($product_cat_ids[0],'product_cat');
			$product_category = $product_cat->slug;

			if ($product_category != 'fixed-fee') {
					 
				continue;
					 
			}
			
			$class_name = esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) );
			$name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;' . wc_get_formatted_cart_item_data( $cart_item );

			$purchase_note = get_post_meta( $_product->get_id(), '_purchase_note', true );
			if( strlen( $purchase_note ) > 0 ) {
				$name = $name . " " . $purchase_note;
			}
			

			$ProdQuant = $cart_item['quantity'];
			if( $_product->get_sale_price() > 0 ) {
				$ProdPrice = $_product->get_price();
				$fees_after = round($ProdPrice * $ProdQuant, 2);
				$fees_before = round($_product->get_regular_price() * $ProdQuant, 2);
				$fees = "<s>" . $fees_before . "</s> " . $fees_after;
			} else {
				$ProdPrice = $_product->get_price();
				$fees = round($ProdPrice * $ProdQuant, 2);
			}	
			return true;
		}

	return false;
}


add_filter( 'default_checkout_billing_state', 'xa_set_default_checkout_state' );
function xa_set_default_checkout_state() {
  // Returns empty state by default.
  //   return null;
  // Returns California as default state.
     return 'ON';
}

add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields',99 );

// Our hooked in function - $fields is passed via the filter!
function custom_override_checkout_fields( $fields ) {
     
     $ubpo = dg_get_user_meta ("upfront_bill_payment_option");
     
     //billing fields not required for email trasfer payment option.
     if ($ubpo=="email-transfer") {
	     
	     foreach($fields['billing'] as $key=>$val) {
		     
		     $fields['billing'][$key]['required'] = false;
	     }
     }
     
     $fields['billing']['billing_email']['default'] = dg_get_user_meta ('email');
     $fields['billing']['billing_first_name']['default'] = dg_get_user_meta ('first_name');
     $fields['billing']['billing_last_name']['default'] = dg_get_user_meta ('last_name');
     
     //var_dump("<h2>i am in</h2>");
     
     if (!empty($ubpo['searched_address']['provinceOrState'])) {
	 	 $fields['shipping']['shipping_state']['default'] = $searched_address['provinceOrState'];    
     }
     
     $fields['billing']['billing_first_name']['label'] = "Billing first name";
     $fields['billing']['billing_last_name']['label'] = "Billing last name";
     $fields['billing']['billing_email']['label'] = "Billing email address";
     $fields['billing']['billing_city']['label'] = "City";
     $fields['billing']['billing_postcode']['label'] = "Postal Code";
     
     
     $fields['billing']['billing_city']['class'][0] = "form-row-first";
     $fields['billing']['billing_state']['class'][0] = "form-row-last";
     
     
     $fields['billing']['billing_postcode']['class'][0] = "form-row-first";
     $fields['billing']['billing_postcode']['class'][1] = "clear";
     $fields['billing']['billing_postcode']['class'][2] = "forceuppercase";
     $fields['billing']['billing_phone']['class'][0] = "form-row-last";
     $fields['billing']['billing_phone']['required'] = true; 
     
     
     unset($fields['billing']['billing_company']);
     unset($fields['billing']['billing_address_2']);
     unset($fields['order']['order_comments']);
     
     
     $fields['billing']['service_address'] = array(
	        'label'     => __('Service Address', 'woocommerce'),
		    'placeholder'   => _x('Service Address', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('searched_street_address')
		);
	
	$fields['billing']['date1'] = array(
	        'label'     => __('1st Preferred Installation Date and Time', 'woocommerce'),
		    'placeholder'   => _x('1st Preferred Installation Date and Time', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('preffered_installation_date_1')." ".dg_get_user_meta ('preffered_installation_time_1')
		);
	
	$fields['billing']['date2'] = array(
	        'label'     => __('2nd Preferred Installation Date and Time', 'woocommerce'),
		    'placeholder'   => _x('2nd Preferred Installation Date and Time', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('preffered_installation_date_2')." ".dg_get_user_meta ('preffered_installation_time_2')
		);
	
	$fields['billing']['date3'] = array(
	        'label'     => __('3rd Preferred Installation Date and Time', 'woocommerce'),
		    'placeholder'   => _x('2rd Preferred Installation Date and Time', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('preffered_installation_date_3')." ".dg_get_user_meta ('preffered_installation_time_3')
		);
	
	$fields['billing']['customer_first_name'] = array(
	        'label'     => __('Customer First Name', 'woocommerce'),
		    'placeholder'   => _x('Customer First Name', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('first_name')
		);
	
	$fields['billing']['customer_last_name'] = array(
	        'label'     => __('Customer Last Name', 'woocommerce'),
		    'placeholder'   => _x('Customer last Name', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('last_name')
		);
	
	$fields['billing']['customer_email'] = array(
	        'label'     => __('Customer email address', 'woocommerce'),
		    'placeholder'   => _x('Customer email address', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('email')
		);
	
	$fields['billing']['customer_phone'] = array(
	        'label'     => __('Customer phone', 'woocommerce'),
		    'placeholder'   => _x('Customer phone', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('phone')
		);

     /*
	$fields['billing']['how_did_you_hear_about_us'] = array(
	        'label'     => __('How did you hear about us', 'woocommerce'),
		    'placeholder'   => _x('How did you hear about us', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('how_did_you_hear_about_us')
		);
      */
	$fields['billing']['referrer_name'] = array(
	        'label'     => __('Referrer name', 'woocommerce'),
		    'placeholder'   => _x('Referrer name', 'placeholder', 'woocommerce'),
		    'required'  => false,
		    'class'     => array('checkout_hidden_fields'),
		    'clear'     => true,
		    'default' 	=> dg_get_user_meta ('referrer_name')
		);
     
     
    return $fields;
}

// Optimized by Eugene with ChatGPT help to update logic that was trying to encrypt with WordPress ID. Remove is_user_logged_in() check and always use get_dg_user_id()
add_action('woocommerce_checkout_update_order_meta', 'saving_checkout_cf_data');
function saving_checkout_cf_data($order_id) {
	if (isset($_POST['checkout']) && is_array($_POST['checkout'])) {
		SendInfoToSignupServer(50);

		foreach ($_POST['checkout'] as $key => $value) {
			if ($key == "monthly_cc" || $key == "monthly_bank") {
				$token = maybe_serialize($value);
				$encryption_key = get_dg_user_id(); // Always use cookie ID
				$cryptor = new Cryptor($encryption_key);
				$value = $cryptor->encrypt($token);
				unset($token);
				update_post_meta($order_id, "dg_user_hash", $encryption_key);
			}

			if (!is_array($value)) {
				update_post_meta($order_id, $key, sanitize_text_field($value));
			} else {
				update_post_meta($order_id, $key, maybe_serialize($value));
			}
		}
	}
}

add_action( 'woocommerce_order_status_on-hold', 'dg_on_order_processing');
add_action( 'woocommerce_order_status_processing', 'dg_on_order_processing');
function dg_on_order_processing($order_id) {
	
	$order = wc_get_order( $order_id );
	$user_email  = dg_get_user_meta ("email");
	$data['source'] = "Website purchase";
	$data['lead_status'] = "Customer signed up";
	
	//hubspot_api_update_contact_by_email($data,$user_email);
	
	dg_set_user_meta ("onboarding_stage","checkout_complete");
	
	
}


/*
function update_order_step($step_number = 0) {

	$values[0] = "0 - Availability";
	$values[1] = "1 - Selected Internet Plan";
	$values[2] = "2 - Customer Details";
	$values[3] = "3 - Service Address";
	$values[4] = "4 - Installation";
	$values[5] = "5 - Phone Plan";
	$values[6] = "6 - Modem Options";
	$values[7] = "7 - Review Order";
	$values[8] = "8 - Checkout";


	return $values[$step_number];

}
 */

function wporg_add_payment_box()
{
    $screens = ['shop_order'];
    foreach ($screens as $screen) {
        add_meta_box(
            'wporg_box_payment',           // Unique ID
            'Payment Details',  // Box title
            'wporg_payment_box_html',  // Content callback, must be of type callable
            $screen                   // Post type
        );
    }
}

add_action('add_meta_boxes', 'wporg_add_payment_box');
function wporg_payment_box_html($post)
{
	$order_meta = get_post_meta($post->ID,"",true);
	 if ( ($order_meta['monthly_bill_payment_option'][0]=="bank" || $order_meta['monthly_bill_payment_option'][0]=="cc")  ):
	 	$k=$order_meta['dg_user_hash'][0];
		$cryptor = new Cryptor($k);
		if($order_meta['monthly_bill_payment_option'][0]=="bank"){
			$field = $order_meta['monthly_bank'][0];
			$heading= "Bank Billing Details";
		}else {
			$field = $order_meta['monthly_cc'][0];
			$heading= "Credit Card Billing Details";
		}
		
		$value = $cryptor->decrypt($field);
		$data=maybe_unserialize($value);
	

    ?>
     	<div class="panel woocommerce-order-data" id="order_data">
			<h2 class="woocommerce-order-data__heading"><?php echo $heading;?></h2>
				<div class="inside">
					<div id="postcustomstuff">
				<?php //echo "<pre>";print_r($data);echo "</pre>";?>
				<?php if(!empty($data)){?>
					<table id="newmeta">
						<thead>
							<tr>
								<th class="left" >Name</th>
								<th>Value</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($data as $key => $value) { ?>
								
							
								<tr>
									<td class="left" id="newmetaleft">
									<label><?php echo $key;?></label>
									</td>
									<td>
									<label><?php echo $value;?></label>
									</td>

								</tr>	
						
							<?php
							}
							?>
							</tbody>
					</table>	
							
				<?php  } ?>
				</div>
			</div>
		
		</div>
    <?php
	endif;    
}

function Cleanup_Number($val) {
	return preg_replace('/[^0-9]/', '', $val);
}

// Eugene define shortcode to render order summary, which will be used on thank-you page
function dg_order_summary_shortcode() {
    $summary = dg_get_user_meta("order_summary_html");
    if (!$summary) return "<p>We couldn’t find your order details. Please contact support.</p>";
    return $summary;
}
add_shortcode('dg_order_summary', 'dg_order_summary_shortcode');
