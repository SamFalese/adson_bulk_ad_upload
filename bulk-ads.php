<?php

add_action('wp_ajax_upload_bulk_images', 'upload_bulk_images_callback');

function upload_bulk_images_callback(){

	if (!empty($_FILES['photos-input'])) {

		$ad_id = isset($_POST['ad_id']) && !empty($_POST['ad_id']) ? $_POST['ad_id'] : 0 ;
		$response = [ 'status' => 'failed', 'heading' => 'Attachment Upload', 'message' => 'Something went wrong!' ];


		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );

		// For Ad
		$files = $_FILES["photos-input"];
		foreach ($files['name'] as $key => $value) {
			if ($files['name'][$key]) {
				$file = array(
					'name' => $files['name'][$key],
					'type' => $files['type'][$key],
					'tmp_name' => $files['tmp_name'][$key],
					'error' => $files['error'][$key],
					'size' => $files['size'][$key]
				);

				$array_ = explode('.', $value);
				$extension = end($array_);
				if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])) {

					$image_type = $_POST['img_type'];

					$_FILES = array("photos-input" => $file);
					$attachment_id = media_handle_upload("photos-input", $ad_id);

					if (is_wp_error($attachment_id)) {
						// There was an error uploading the image.
						$response['message'] =  $attachment_id->get_error_message();
					} else {
						// The image was uploaded successfully!
						$response['status'] =  'success';
						$response['message'] =  'Uploaded successfully!';
						$attachment_url = wp_get_attachment_url($attachment_id);
						$response['attachments'][] =  $attachment_url;
						if($image_type == 'featured-image[]'){
							wp_delete_attachment(get_post_thumbnail_id($ad_id),true);
							set_post_thumbnail( $ad_id, $attachment_id );
						}else if($image_type == 'gallery-images[][]'){
							add_post_meta($ad_id, 'files3', $attachment_url);
						}
					}
					$response['attachment_id'] =  $attachment_id;

				}else{
					 $response['message'] =  'Image format not supported!';
				}
			}
		}
	}

	echo json_encode($response);

	wp_die();

}
add_action('wp_ajax_delete_bulk_ads_attachment', 'delete_bulk_ads_attachment_callback');
function delete_bulk_ads_attachment_callback(){
	$type_name = $_POST['type_name'];
	$ad_id = $_POST['ad_id'];
	$url = $_POST['url'];
	$response = [ 'status' => 'failed', 'heading' => 'Attachment Delete', 'message' => 'Something went wrong!' ];

	global $wpdb;
	$attachment_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE guid='$url'");
	if($attachment_id > 0){
		if ($type_name == 'featured-image[]') {
			delete_post_thumbnail($ad_id);
			wp_delete_attachment($attachment_id);
		} else if ($type_name == 'gallery-images[][]') {
			$wpdb->delete( $wpdb->postmeta, [
		        'post_id' => $ad_id,
		        'meta_key' => 'files3',
		        'meta_value' => $url,
		    ]);
			wp_delete_attachment($attachment_id);
		}
		$response['status'] =  'success';
		$response['message'] =  'Media delteed successfully!';
	}else $response['message'] =  'Attachment not found!';

	echo json_encode($response);
	wp_die();
}


add_action('wp_ajax_bulk_ads_upload', 'bulk_ads_upload_callback');
add_action('wp_ajax_nopriv_bulk_ads_upload', 'bulk_ads_upload_callback');
function bulk_ads_upload_callback(){

	$response = [ 'status' => 'failed', 'heading' => 'Bulk Ads Upload', 'message' => 'Something went wrong!' ];
	if (isset($_POST)) {

		global $wpdb;

		$is_draft = $_POST['is_draft'];
		$status = $is_draft ? 'draft' : 'pending';

		// Check Parent POST
		$PP_ID = $_POST['parent_bulk_id'];
		if ( get_post_status ( $PP_ID  ) ) {
			$Parent_ID = parent_update_post($PP_ID,wp_strip_all_tags($_POST['ads_name']),'pending');
		}else {
			$Parent_ID = parent_insert_post(wp_strip_all_tags($_POST['ads_name']),'pending');
		}

		for($i = 0; $i < count($_POST['brand_name']); $i++) {

			$ad_id 		 = wp_strip_all_tags($_POST['ad_id'][$i]);
			$title 		 = wp_strip_all_tags($_POST['title'][$i]);
			$description = wp_strip_all_tags($_POST['description'][$i]);

			$Item_Ad = get_post($ad_id);
			if(count($Item_Ad) > 0){
				$wpdb->query("UPDATE ".$wpdb->prefix."posts SET post_title='$title', post_content='$description' WHERE ID=$ad_id");
			} else {
				$ad_id = add_blank_ad($Parent_ID, 0, $status);
			}

		    $user_id = get_current_user_id();
			if(isset($_POST['assign_compnay_user_id']) && $_POST['assign_compnay_user_id'] > 0) {
		        $user_id = $_POST['assign_compnay_user_id'];
		    }
			$wpdb->query("UPDATE ".$wpdb->prefix."posts SET post_author='".$user_id."' WHERE ID=$ad_id");


			if($is_draft == 'yes') {
				// $wpdb->query("UPDATE ".$wpdb->prefix."posts SET post_status='draft' WHERE ID=$ad_id");
				$wpdb->query("UPDATE ".$wpdb->prefix."posts SET post_status='draft' WHERE ID=$Parent_ID");

			}

			$type = wp_strip_all_tags($_POST['type'][$i]);
			wp_set_post_terms($ad_id, $type, 'type');

			$category = wp_strip_all_tags($_POST['category'][$i]);
			wp_set_post_terms($ad_id, $category, 'category');

			$brand_name 	= wp_strip_all_tags($_POST['brand_name'][$i]);
			update_post_meta($ad_id, 'brand_name', $brand_name);

			$brand_website 	= wp_strip_all_tags($_POST['brand_website'][$i]);
			update_post_meta($ad_id, 'brand_website', $brand_website);
			
			$country 	= wp_strip_all_tags($_POST['country'][$i]);
			update_post_meta($ad_id, 'country', $country);


			$publish_date = wp_strip_all_tags($_POST['publish_date'][$i]);
			$publish_date = explode('-', $publish_date);
			$year  = $publish_date[0];
			$month = date('m', strtotime($publish_date[1])) ;
			update_post_meta($ad_id, 'month', $month);
			update_post_meta($ad_id, 'year', $year);

			$files = $_POST['files'][$ad_id];
			update_post_meta($ad_id, 'files', $files);

			$user_a  = $_POST['user_a'][$ad_id];
			$credits = [];
			// if(!empty($user_a['type'][0])){
			foreach ($user_a['type'] as $key => $user_v) {
				if(!empty($user_a['type'][$key])) {
					if($user_a['type'][$key] == 'company'){
						$credit = [
							'company_name' => $user_a['company_name'][$key],
							'company_email' => $user_a['company_email'][$key],
							'company_type' => $user_a['company_type'][$key],
						];
					}else{
						$credit = [
							'job' => $user_a['job'][$key],
							'other' => $user_a['other'][$key],
							'name' => $user_a['name'][$key],
							'email' => $user_a['email'][$key],
						];
					}
					$credit['type'] = $user_a['type'][$key];
					$credits[] = $credit;
				}
			}
			// }

			if (!empty($credits)) {
				delete_post_meta($ad_id, 'project_user');
				delete_post_meta($ad_id, 'user__');
				foreach ($credits as $project_user) {
					//if(!empty($project_user['email'])){
						add_post_meta($ad_id, 'project_user', $project_user);
						// $user_id = send_team_activate($project_user['email'], $ad_id);
						$user_id = ads_credit_email($project_user['email'], $ad_id);
						add_post_meta($ad_id, 'user__', $user_id);
						add_user_meta($user_id, 'post__', $ad_id);
						if (!empty($project_user['user_id'])) {
							add_post_meta($ad_id, 'user__', $project_user['user_id']);
							add_user_meta($project_user['user_id'], 'post__', $ad_id);
						}
					//}
				}
			}

		}

		$active = 'bulk_ads';
		if($is_draft == 'yes') {
			$active = 'drafts';
		}
		$redirect = add_query_arg('active', $active, get_permalink(1518));

    	$response['status'] =  'success';
    	$response['message'] =  'Bulk Ads Uploaded';
    	$response['parent_id'] =  $Parent_ID;
    	$response['redirect'] =  $redirect;
	}

	echo json_encode($response);
	wp_die();
}


/**
 *
 * Add Blank Ad for Bulk Ads
 */
add_action('wp_ajax_add_blank_bulk_ad', function(){
	$parent_ID = wp_strip_all_tags($_POST['ad_id']);
	$title_ad = wp_strip_all_tags($_POST['title_ad']);
	/*if(empty($parent_ID)){
		$parent_ID = parent_insert_post($title_ad);
	}*/
	$add_blank = add_blank_ad($parent_ID);
	if($add_blank){
		$html = get_bulk_ad_html($add_blank);
		if(!empty($parent_ID)){
			$response = [ 'status' => 'success', 'heading' => 'Ad Added', 'message' => 'Blank Ad Added Successfully!' ,'parent_ID' => $parent_ID , 'html'=> $html ];
		}else{
			$response = [ 'status' => 'false', 'heading' => 'Ad Added', 'message' => 'Blank Ad not added!' ,'parent_ID' => $parent_ID , 'html'=> $html ];
		}
		echo json_encode($response);
	}
	wp_die();
});

/**
 *
 * Delete  Ad from Bulk Ads
 */
add_action('wp_ajax_remove_bulk_ad', function(){
	$response = [ 'status' => 'failed', 'heading' => 'Delete Ad', 'message' => 'Something went wrong!' ];
	$ad_id = $_POST['ad_id'];
	$deleted = wp_delete_post($ad_id, true);

	if($deleted){
		$response['status'] = 'success';
		$response['message'] = 'Ad deleted successfully!';
	}else $response['message'] = 'Ad not deleted!';

	echo json_encode($response);
	wp_die();
});

/**
 *
 * duplicate Ad for Bulk Ads
 */
add_action('wp_ajax_duplicate_bulk_ad', function(){
	$response = [ 'status' => 'failed', 'heading' => 'Ad Cloned', 'message' => 'Something went wrong!' ];
	$ad_id = $_POST['ad_id'];
	$parent_id = $_POST['parent_id'];
	$new_ad_id = duplicate_bulk_ad($ad_id, $parent_id);
	if($new_ad_id > 0){
    	$response['html'] = get_bulk_ad_html($new_ad_id);
		$response['status'] = 'success';
		$response['message'] = 'Ad Cloned successfully!';
		$response['new_ad_id'] = $new_ad_id;
	}else $response['message'] = 'Something went worng while Ad Cloning!';
	echo json_encode($response);
	wp_die();
});

function duplicate_bulk_ad($ad_id, $parent_id = 0){

	if (!$ad_id) return;

	// $new_ad_id = add_blank_ad(0, $ad_id);
	$new_ad_id = add_blank_ad($parent_id);
	$dontcopy = ['year', 	'month', '_thumbnail_id', 'files', 'files2', 'files3', 'files4'];


    $data = get_post_custom($ad_id);
    foreach ( $data as $key => $values) {
    	if(!in_array($key, $dontcopy)) { // skip "don't clone" fields
	      foreach ($values as $value) {
	        add_post_meta( $new_ad_id, $key, maybe_unserialize( $value ) );
	      }
    	}
    }

    return $new_ad_id;
}


function add_blank_ad($Parent_ID = 0, $ad_id = 0, $status = 'pending')
{
	$user_id = get_current_user_id();
	if(isset($_POST['assign_compnay_user_id']) && $_POST['assign_compnay_user_id'] > 0) {
        $user_id = $_POST['assign_compnay_user_id'];
    }
	$temp_bulk_id = get_user_meta($user_id, 'temp_bulk_id', true);

	$type = $category = $content = '';
	if ($ad_id > 0) { //user for dublicate ad
		$ad = get_post($ad_id);
		if ($ad) {
			$title = $ad->post_title;
			$content = $ad->post_content;
			$Parent_ID = $ad->post_parent;
			$type = get_the_terms($ad_id, 'type');
			$category = get_the_terms($ad_id, 'category');
		}
	}
	$title = $title ? $title : 'Blank Ad';


	$ad_id = wp_insert_post([
		'post_title' => $title,

		'post_content' => $content,

		'post_status' => 'pending',

		'post_author' => $user_id,

		'post_type' => 'post',

		'post_parent' => $Parent_ID
	]);
	$metaData = get_post_meta($ad_id);
	if(!empty($metaData)){
	    foreach($metaData as $key=>$val)  {
	        delete_post_meta($ad_id, $key);
	    }
	}

	update_post_meta($ad_id, 'bulk_id', $temp_bulk_id);
	update_post_meta($ad_id, 'ads_type', 'bulk_ad');

	if (!empty($type)) {
		wp_set_post_terms($ad_id, $type[0]->term_id, 'type');
	}
	if (!empty($category)) {
		wp_set_post_terms($ad_id, $category[0]->term_id, 'category');
	}

	return $ad_id;

}


function get_credit_field_html($ad_id = 0, $creditData = [], $show_delete = false)
{
	$type  = isset($creditData['type'])  ? $creditData['type']  : '';
	$company_name  = isset($creditData['company_name']) ? $creditData['company_name'] : '';
	$company_email = isset($creditData['company_email']) ? $creditData['company_email'] : '';
	$company_type = isset($creditData['company_type']) ? $creditData['company_type'] : '';
	$job   = isset($creditData['job'])   ? $creditData['job']   : '';
	$other = isset($creditData['other']) ? $creditData['other'] : '';
	$name  = isset($creditData['name'])  ? $creditData['name']  : '';
	$email = isset($creditData['email']) ? $creditData['email'] : '';

 	ob_start(); ?>
 		<div class="project_user">
	        <div>
	        	<div class="form-group">
	                <label class="label_frm">تصنيف المشارك  </label>
	                <?php
		              $ssCompany = $ssIndiviual = '';
	                if($type == 'individual') {
		                $ssIndiviual = 'selected';
	                }
	                if($type == 'company') {
	                	$ssCompany = 'selected';
	                } ?>
	                <select name="user_a[<?= $ad_id ?>][type][]" class="form-control js-select c_user_type" data-placeholder="اختر نوع المشارك" >
	                    <option value="" >تصنيف المشارك    </option>
	                    <option value="individual" <?php echo $ssIndiviual ?> >أفراد</option>
	                    <option value="company" <?php echo $ssCompany; ?> >شركات</option>
	                </select>
	                <span class="frm_icon frm_user"></span>
	            </div>
	            <div class="individual-field type" style="display:<?php echo $ssIndiviual ? 'block' : 'none'; ?>" >
		            <div class="form-group">
		                <label class="label_frm">المسمى الوظيفي</label>
		                <select name="user_a[<?= $ad_id ?>][job][]" class="form-control js-select job_title_" data-placeholder="اختر المسمى الوظيفي">
		                    <option></option>
		                    <?php foreach (get_job_names() as $job_id => $job_name) {
		                    	$sell = $job  == $job_id ? 'selected' : '';
		                        echo '<option value="'.$job_id.'" '.$sell.'>'.$job_name.'</option> ';
		                    } ?>
		                </select>
		                <span class="frm_icon frm_user"></span>
		            </div>
		            <div class="form-group other_" style="<?php echo $job == 'other' ? '' : 'display:none'; ?>">
		                <label class="label_frm">المسمى الوظيفي</label>
		                <input  name="user_a[<?= $ad_id ?>][other][]" required type="text"  class="form-control other_input"  placeholder="أكتب المسمى الوظيفي">
		                <span class="frm_icon frm_user"></span>
		            </div>
	                <div class="form-group">
	                    <label class="label_frm">الاسم</label>
	                    <input name="user_a[<?= $ad_id ?>][name][]" value="<?= $name; ?>" type="text" class="form-control" placeholder="اكتب الاسم هنا">
	                    <span class="frm_icon frm_user"></span>
	                </div>
					<div class="form-group">
		                <label class="label_frm"> البريد الإلكتروني </label>
		                <input  name="user_a[<?= $ad_id ?>][email][]"  type="email" value="<?= $email; ?>" class="form-control uc_email"  placeholder=" example@example.com ">
		                <span class="frm_icon frm_email"></span>
		            </div>
		        </div>
		        <div class="company-field type" style="display:<?php echo $ssCompany ? 'block;':'none'; ?>;" >
		            <div class="form-group">
		                <label class="label_frm">اسم الشركة </label>
		                <input  name="user_a[<?= $ad_id ?>][company_name][]"  type="text" value="<?= $company_name; ?>" class="form-control"  placeholder="اسم الشركة ">
		                <span class="frm_icon frm_user"></span>
		            </div>
					<div class="form-group">
		                <label class="label_frm">ايميل الشركة  </label>
		                <input  name="user_a[<?= $ad_id ?>][company_email][]"  type="email" value="<?= $company_email; ?>"  class="form-control uc_email"  placeholder=" example@example.com ">
		                <span class="frm_icon frm_email"></span>
		                <label class="error"></label>
		            </div>
					<div class="form-group">
	                    <label class="label_frm">مجال العمل</label>
	                    <select name="user_a[<?= $ad_id ?>][company_type][]" required class="form-control js-select" data-placeholder="اختر مجال العمل">
	                        <option></option>
	                        <?php
	                        $ts = get_terms(['taxonomy'=>'work-type','hide_empty'=>0]);
	                        if($ts){
	                            foreach ($ts as $t){
	                            	$sell = $t->term_id  == $company_type ? 'selected' : '';
	                                echo '<option value="'.$t->term_id.'"  '.$sell.'>'.$t->name.'</option>';
	                            }
	                        }
	                        ?>

	                    </select>
	                    <span class="frm_icon frm_computer"></span>
	                </div>
	            </div>
	        </div>
	    </div>
	    <?php //if ($show_delete || !empty($email) || !empty($company_email)) { ?>
	    <?php if ($show_delete) { ?>
	    	<button type="button" class="btn btn-danger delete_user "><img src="https://ads-on.co/wp-content/uploads/2022/03/delete-icon-bulk-ads.svg"></button>
	    <?php } ?>
	<?php return ob_get_clean();

}

function get_video_field_html($ad_id, $newField=false, $value = "")
{
	ob_start(); ?>

	<label class="label_frm">رابط الفيديو </label>
    <input  name="files[<?= $ad_id ?>][]" required type="text" class="form-control video-input" value="<?php echo $value; ?>"  placeholder="رابط لفيديو من يوتيوب أو فيميو">
	<?php if($newField == true || !empty($value)){ ?>
		<button type="button" class="btn btn-danger  remove_video mb-3" >
		<img src="<?php echo get_bloginfo("stylesheet_directory") ?>/images/bin.svg"></button>
	<?php } ?>
	<?php $videoField = ob_get_clean();

	ob_start(); ?>
	<div class="form-group new_video_url m-0">
		<?php echo $videoField; ?>
	</div>
	<?php $newVideoField = ob_get_clean();

	if($newField == true)
		return $newVideoField;
	else
		return $videoField;

}

function get_bulk_ad_html($ad_id = 0, $parent_ID = 0)
{
	if(empty($ad_id)){
		$ad_id = add_blank_ad($parent_ID);
	}else{
		$ad = get_post($ad_id);
		if(empty($ad)){ return; }
	}

	global $wpdb;
	$title =  get_the_title($ad_id);
	$title = $title == 'Blank Ad' ? '' : $title;
	$description = get_the_content(null, false, $ad_id);

	$company_name =  get_post_meta($ad_id, 'company_name', true);
	$company_name = !empty($company_name) ? $company_name : '';

	$mobile =  get_post_meta($ad_id, 'mobile', true);
	$mobile = !empty($mobile) ? $mobile : '';

	$email =  get_post_meta($ad_id, 'email', true);
	$email = !empty($email) ? $email : '';

	$work_type =  get_post_meta($ad_id, 'work_type', true);
	$work_type = !empty($work_type) ? $work_type : '';

	$website =  get_post_meta($ad_id, 'website', true);
	$website = !empty($website) ? $website : '';

	$twitter =  get_post_meta($ad_id, 'twitter', true);
	$twitter = !empty($twitter) ? $twitter : '';

	$brand_name =  get_post_meta($ad_id, 'brand_name', true);
	$brand_name = !empty($brand_name) ? $brand_name : '';

	$brand_website =  get_post_meta($ad_id, 'brand_website', true);
	$brand_website = !empty($brand_website) ? $brand_website : '';

	$country =  get_post_meta($ad_id, 'country', true);
	$country = !empty($country) ? $country : '';

	$social_link =  get_post_meta($ad_id, 'social_link', true);
	$social_link = !empty($social_link) ? $social_link : '';

	$month =  get_post_meta($ad_id, 'month', true);
	$month = !empty($month) ? $month : '';

	$year =  get_post_meta($ad_id, 'year', true);
	$year = !empty($year) ? $year : '';

	$publish_date = "$year-$month";
	// if($publish_date == '-'){
	// 	$publish_date = "2000-01";
	// }

	$featured_image = wp_get_attachment_url(get_post_thumbnail_id($ad_id));

	$files  =  get_post_meta($ad_id, 'files', true);
	$files = !empty($files) ? $files : '';

	$files2 =  get_post_meta($ad_id, 'files2', true);
	$files2 = !empty($files2) ? $files2 : '';

	$files3 =  get_post_meta($ad_id, 'files3');
	$files3 = !empty($files3) ? $files3 : '';

	$files4 =  get_post_meta($ad_id, 'files4');
	$files4 = !empty($files4) ? $files4 : '';

	$project_users =  get_post_meta($ad_id, 'project_user');
	$project_users = !empty($project_users) ? $project_users : '';

	$ads_type =  wp_get_post_terms($ad_id, 'type',['fields'=>'ids']);
	$ads_type = !empty($ads_type) ? $ads_type : [];

	$ads_category = wp_get_post_terms($ad_id, 'category',['fields'=>'ids']);
	$ads_category = !empty($ads_category) ? $ads_category : [];

	$ads_brand = wp_get_post_terms($ad_id, 'brand',['fields'=>'ids']);
	$ads_brand = !empty($ads_brand) ? $ads_brand : '';

	$ads_tags = wp_get_post_terms($ad_id, 'post_tag');
	$ads_tags = !empty($ads_tags) ? $ads_tags : '';

	$RandNum = rand(1,999999);

 	ob_start();  ?>

	<div class="bulk-ad-row" style="" id="rows_in-<?php echo $ad_id; ?>">
	<div class="col-md-3">
		<div class="form-group other_brand">
			<!--<label class="label_frm">اسم العلامة التجارية </label>-->
			<input type="text" required="" readonly class="form-control brand_name_t" value="<?= $brand_name; ?>" data-id="<? echo $RandNum; ?>" placeholder="إسم العلامة التجارية">
			<span class="frm_icon frm_crown"></span>
		</div>
	</div>
	<div class="brand_details modalads" id="brand_<? echo $RandNum; ?>">
	<button type="button" class="btn_close_modal close_top" data-dismiss="modal" aria-label="Close"><i class="fal fa-times"></i></button>
		<div class="col-md-3">
    		<div class="form-group other_brand">
                <!--<label class="label_frm">اسم العلامة التجارية </label>-->
                <input type="text" name="brand_name[]" value="<?= $brand_name; ?>" required="" class="form-control brand_name" placeholder="إسم العلامة التجارية">
                <span class="frm_icon frm_crown"></span>
            </div>
        </div>
		<div class="col-md-3">
			<div class="form-group">
				<!--<label class="label_frm">مجال العلامة التجارية</label>-->
				<select name="type[]" required class="form-control js-select type_domaine" data-placeholder="مجال العلامة التجارية">
				<?php $ts2 = get_terms(['taxonomy'=>'type','hide_empty'=>0]);
					if($ts2){ foreach ($ts2 as $t){ ?>
						<option <?php echo in_array($t->term_id ,$ads_type)?'selected':''; ?> value="<?php echo $t->term_id; ?>" ><?php echo $t->name; ?></option>
						<?php }
					} ?>
				</select>
				<span class="frm_icon frm_a-tag"></span>
			</div>
		</div>
		<div class="col-md-3">
			<div class="form-group">
				<!--<label class="label_frm">معرف المنصات الإجتماعية / رابط الموقع </label>-->
				<input type="text" name="brand_website[]" value="<?= $brand_website; ?>" required="" class="form-control brand_website" placeholder="http://website.com">
				<span class="frm_icon frm_globe"></span>
			</div>
		</div>
		<div class="row  mt-3  mb-3" style="margin: 0 auto;">
			<div class="col-md-2">
				<button class="btn btn-block btn_blue btn_sm Save_Close_Modal" type="button"> <abbr> Save  </abbr>  <span></span><span></span><span></span><span></span></button>
			</div>
			<div class="col-md-2">
				<button class="btn btn-block btn_blue  btn_sm Clear_Close_Modal"  type="button"><abbr> Clear  </abbr>  <span></span><span></span><span></span><span></span></button>
			</div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			<!--<label class="label_frm">عنوان الحملة الرئيسي</label>-->
			<input type="text" class="form-control camp_title" value="<?= $title; ?>" required readonly data-id="<? echo $RandNum; ?>" placeholder="عنوان الحملة الرئيسي">
			<span class="frm_icon frm_player"></span>
		</div>
	</div>
	<div class="campg_details modalads" id="campg_<? echo $RandNum; ?>">
	<button type="button" class="btn_close_modal close_top" data-dismiss="modal" aria-label="Close"><i class="fal fa-times"></i></button>
        <div class="col-md-3">
        	<div class="form-group">
				<!--<label class="label_frm">عنوان الحملة الرئيسي</label>-->
				<input type="text" name="title[]" value="<?= $title; ?>" class="form-control title" required placeholder="عنوان الحملة الرئيسي">
				<span class="frm_icon frm_player"></span>
			</div>
        </div>
        <div class="col-md-3">
        	<div class="form-group">
				<!--<label class="label_frm">نوع الإعلان</label>-->
				<select name="category[]" required class="form-control js-select category_select" data-placeholder="اختر مجال العمل">
					<option value=""></option>
                   	<?php $ts3 = get_terms(['taxonomy'=>'category','hide_empty'=>0]);
                    if($ts3){
                        foreach ($ts3 as $t){ ?>
                            <option <?php echo in_array($t->term_id ,$ads_category)?'selected':''; ?> value="<?php echo $t->term_id; ?>"><?php echo $t->name; ?></option>
                        <?php }
                    } ?>
				</select>
				<span class="frm_icon frm_target"></span>
			</div>
        </div>
        <div class="col-md-3">
        	<div class="form-group">
				<!--<label class="label_frm">نوع الإعلان</label>-->
				<select name="country[]" required class="form-control js-select " data-placeholder="اختر بلدك">
					<option value=""></option>
					<option <?php echo $country == 'Algeria' ? 'selected' : ''; ?> >Algeria</option>
					<option <?php echo $country == 'Bahrain' ? 'selected' : ''; ?> >Bahrain</option>
					<option <?php echo $country == 'Egypt' ? 'selected' : ''; ?> >Egypt</option>
					<option <?php echo $country == 'Iran' ? 'selected' : ''; ?> >Iran</option>
					<option <?php echo $country == 'Iraq' ? 'selected' : ''; ?> >Iraq</option>
					<option <?php echo $country == 'Jordan' ? 'selected' : ''; ?> >Jordan</option>
					<option <?php echo $country == 'Kuwait' ? 'selected' : ''; ?> >Kuwait</option>
					<option <?php echo $country == 'Lebanon' ? 'selected' : ''; ?> >Lebanon</option>
					<option <?php echo $country == 'Libya' ? 'selected' : ''; ?> >Libya</option>
					<option <?php echo $country == 'Morocco' ? 'selected' : ''; ?> >Morocco</option>
					<option <?php echo $country == 'Oman' ? 'selected' : ''; ?> >Oman</option>
					<option <?php echo $country == 'Qatar' ? 'selected' : ''; ?> >Qatar</option>
					<option <?php echo $country == 'Saudi Arabia' ? 'selected' : ''; ?> >Saudi Arabia</option>
					<option <?php echo $country == 'Palestine' ? 'selected' : ''; ?> >Palestine</option>
					<option <?php echo $country == 'Syria' ? 'selected' : ''; ?> >Syria</option>
					<option <?php echo $country == 'Tunisia' ? 'selected' : ''; ?> >Tunisia</option>
					<option <?php echo $country == 'United Arab Emirates' ? 'selected' : ''; ?> >United Arab Emirates</option>
					<option <?php echo $country == 'Yemen' ? 'selected' : ''; ?> >Yemen</option>
				</select>
				<span class="frm_icon frm_globe"></span>
			</div>
        </div>
        <div class="col-md-3">
        	<div class="form-group textarea_input" style="height: auto;">
				<!--<label class="label_frm">وصف الحملة</label>-->
				<!-- <input type="text" onkeyup="countChar(this,2000,'.counterID_<? echo $ad_id; ?>')" name="description[]" class="form-control description" value="<?= $description; ?>" required  placeholder="وصف الحملة "  > -->
				<textarea style="overflow: auto;height: auto;" class="form-control"  onkeyup="countChar(this,2000,'.counter_')"  required name="description" placeholder="وصف الحملة" ></textarea>
				<span class="frm_icon frm_playlist"></span>
                <div class="counter_ counterID_<? echo $ad_id; ?>"></div>
			</div>
        </div>
        <!-- <div class="col-md-3">
					<div class="form-group">
						<input type="month" max="<?php echo date('Y-m'); ?>" min="<?php echo date('1990-01'); ?>" class="publish_date" name="publish_date[]" value="<?php echo $publish_date; ?>" >
					  <span class="frm_icon frm_player"></span>
					</div>
				</div> -->
				<div class="col-md-3">
					<div class="form-group">
						<input type="text" class="form-control text-right datepicker publish_date" name="publish_date[]" value="<?php echo $publish_date; ?>" >
					  <span class="frm_icon frm_player"></span>
					</div>
				</div>
		<div class="row  mt-3  mb-3" style="margin: 0 auto;">
			<div class="col-md-2">
				<button class="btn btn-block btn_blue btn_sm Save_Close_Modal" type="button"> <abbr> Save  </abbr>  <span></span><span></span><span></span><span></span></button>
			</div>
			<div class="col-md-2">
				<button class="btn btn-block btn_blue  btn_sm Clear_Close_Modal"  type="button"><abbr> Clear  </abbr>  <span></span><span></span><span></span><span></span></button>
			</div>
		</div>
	</div>

        <div class="col-md-2">
        	<button class="btn btn-block btn_blue btn_anim show-credit-user-modal" type="button" > <abbr><img src="<?php echo get_site_url(); ?>/wp-content/themes/adson/images/user.svg" /></abbr>  <span></span><span></span><span></span><span></span></button>
        	<div class="modal fade modal_st2" tabindex="-1" role="dialog" aria-hidden="true">
			  	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
				    <div class="modal-content">
				      	<button type="button" class="btn_close_modal close_top" data-dismiss="modal" aria-label="Close"><i class="fal fa-times"></i></button>
						<div class="content_modal_ads">
							<div class="bkk_title">
								<h3>المشاركين في التنفيذ</h3>
							</div>
                            <?php $rr = rand(11111,99999);?>
							<div class="user___list">
								<?php
								if (!empty($project_users)) {

									foreach ($project_users as $i => $project_user) {
										$show_delete = $i == 0 ? false : true;
										echo get_credit_field_html($ad_id, $project_user, $show_delete);
									}
								}else echo get_credit_field_html($ad_id); ?>
                            </div>

                            <div class="group_input_add_nw">
								<a href="javascript:;" class="btn_add_person" data-ad_id="<?= $ad_id; ?>" ><i class="fal fa-plus"></i>اضافة مشارك جديد</a>
							</div>

							<div class="row  mt-3  mb-3">
								<div class="col-md-2">
									<button class="btn btn-block btn_blue btn_sm save_close" type="button"> <abbr> Save  </abbr>  <span></span><span></span><span></span><span></span></button>
								</div>
								<div class="col-md-2">
									<button class="btn btn-block btn_blue reset btn_sm"  type="button"><abbr> Clear  </abbr>  <span></span><span></span><span></span><span></span></button>
								</div>
							</div>

						</div>
				    </div>
			  	</div>
			</div>
		</div>
        <div class="col-md-2">
        	<button class="btn btn-block btn_blue btn_anim show-media-modal" type="button"  ><abbr><img src="<?php echo get_site_url(); ?>/wp-content/uploads/2022/03/media-icon-bulk-ads.svg" /></abbr>  <span></span><span></span><span></span><span></span></button>
			<div class="modal fade modal_st2 media-modal " tabindex="-1" role="dialog" aria-hidden="true">
			  	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
				    <div class="modal-content">
				      	<button type="button" class="btn_close_modal close_top" data-dismiss="modal" aria-label="Close"><i class="fal fa-times"></i></button>
						<div class="content_modal_ads" id="ForMediaUpload">

							<div class="bkk_title">
								<h3>الوسائط</h3>
								<p>يتم رفع مجموعة وسائط مثل: فيديو - تصاميم - إعلان فيديو</p>
							</div>

                                <?php
                                if(!empty($files)){
                                	foreach($files as $key => $fileurl){
                                		$class = $key == 0 ? 'video_input' : 'new_video_url m-0'; ?>
                                		<div class="form-group <?php $class; ?>">
                                			<?php echo get_video_field_html($ad_id, '', $fileurl); ?>
                                		</div>
                                	<?php }
                                } else { ?>
	                            	<div class="form-group video_input  ">
	                                	<?php echo get_video_field_html($ad_id); ?>
	                                </div>
                                <?php } ?>
                            <div class="group_input_add_nw">
								<a href="javascript:;" class="btn_add_video btn_add" data-ad_id="<?= $ad_id; ?>" ><i class="fal fa-plus"></i> أضف رابط فيديو  </a>
							</div>
							<div class="form-group input_upload sound_ads" style="display: none">
								<label class="label_frm">اعلان صوتي</label>
								<div class="input_upload_placeholder"><span class="del_txt">رفع</span> إعلان صوتي لاتتعدى مساحته 300 ميغا MP3 ويمكن تحميل أكثر من صوت  </div>
								<div class="box_upload_file">
									<input type="file" class="file_st1 sound-input" name="files2[]"  >
									<span><img src="<?php bloginfo('stylesheet_directory');?>/images/upload-data.svg">رفع</span>
						            <button type="button" class="btn btn-danger delete_file "><img src="<?php bloginfo('stylesheet_directory');?>/images/bin.svg"></button>
						            <!--RemoveAudoFieldBtn-->
								</div>
							</div>
							<div class="group_input_add_nw">
								<a href="javascript:;" class="btn_add_audio btn_add" style="display:none;"><i class="fal fa-plus"></i>أضف صوتًا  </a>
							</div>
							<div class="form-group input_upload" id="multimage">
								<label class="label_frm">صور</label>
								<div class="input_upload_placeholder"><span class="del_txt">بإمكانك</span> رفع أكثر من صورة لاعلانك</div>
								<div class="box_upload_file">
									<input type="file" class="file_st1 photos-input" name="gallery-images[][]" multiple accept=".jpg, .jpeg, .png, .gif" >
									<span><img src="<?php bloginfo('stylesheet_directory');?>/images/upload-data.svg">رفع</span>
                                    <button type="button" class="btn btn-danger delete_file "><img src="<?php bloginfo('stylesheet_directory');?>/images/bin.svg"></button>
                                </div>
							</div>
                            <div class="imgs__ d-flex justify-content-start align-items-start flex-wrap">
                                    <?php $ii = 0;
								if(!empty($files3)){
                                foreach ($files3 as $im3){?>
                                    <div><img src="<?php echo $im3; ?>" width="150" /> <a href="javascript:;" data-ad_id="<?= $ad_id; ?>" data-url="<?php echo $im3; ?>" data-type_name="gallery-images[][]" class="delete_attch" >حذف الصورة</a></div>
									<?php }} ?>
                            </div>
							<div class="form-group input_upload" id="thumb">
								<label class="label_frm">الصورة البارزة</label>
								<div class="input_upload_placeholder"><span class="del_txt">حدد</span> صورة الإعلان الرئيسية (اختر صورة جذابة بدقة عالية)</div>
								<div class="box_upload_file">
									<input type="file" class="file_st1 photos-input" name="featured-image[]" required accept=".jpg, .jpeg, .png, .gif">
									<span><img src="<?php bloginfo('stylesheet_directory');?>/images/upload-data.svg">رفع</span>
                                    <button type="button" class="btn btn-danger delete_file For_Thumb"><img src="<?php bloginfo('stylesheet_directory');?>/images/bin.svg"></button>
                                </div>
							</div>
                            <div class="imgs__ d-flex justify-content-start align-items-start flex-wrap">
                            	<?php if(!empty($featured_image)){ ?>
	                            	<div>
	                            		<img src="<?php echo $featured_image; ?>" width="150" />
	                            		<a href="javascript:;" data-ad_id="<?= $ad_id; ?>" data-url="<?php echo $featured_image; ?>" data-type_name="featured-image[]" class="delete_attch" >حذف الصورة</a>
	                            	</div>
	                            <?php } ?>
                            </div>
							<div class="row  mt-3  mb-3">
								<div class="col-md-2">
									<button class="btn btn-block btn_blue btn_sm save_close" type="button"> <abbr> Save  </abbr>  <span></span><span></span><span></span><span></span></button>
								</div>
								<div class="col-md-2">
									<button class="btn btn-block btn_blue  btn_sm clear_button"  type="button"><abbr> Clear  </abbr>  <span></span><span></span><span></span><span></span></button>
								</div>
							</div>
						</div>
				    </div>
			  	</div>
			</div>
		</div>
		<div class="col-md-1">
        	<button class="btn btn-block btn_blue btn_anim duplicate-ad-btn" type="button" style="background-color: #ffc107;" ><abbr><img src="<?php echo get_site_url(); ?>/wp-content/uploads/2022/03/duplicate-icon-bulk-ads.svg" /></abbr><span></span><span></span><span></span><span></span></button>
        </div>
        <div class="col-md-1">
        	<button class="btn btn-block btn_blue btn_anim remove-ad-btn" type="button" style="background-color: #dc3545;" > <abbr><img src="<?php echo get_site_url(); ?>/wp-content/uploads/2022/03/delete-icon-bulk-ads.svg" /></abbr> <span></span><span></span><span></span><span></span></button>
        </div>
        <input type="hidden" name="ad_id[]" class="ad_id" value="<?= $ad_id; ?>" >
	</div>

	<?php
	return ob_get_clean();

}

/*
add_filter('manage_post_posts_columns', function($columns) {
	return array_merge($columns, ['ad_type' =>'Ad Type']);
});

add_action('manage_post_posts_custom_column', function($column_key, $post_id) {
	if ($column_key == 'ad_type') {
		$ad_type = get_post_meta($post_id, 'bulk_id', true);
		if ($ad_type > 0) {
			echo 'Bulk';
		} else {
			echo 'Single';
		}
	}
}, 10, 2);

add_action('restrict_manage_posts','restrict_listings_by_business');
function restrict_listings_by_business() {
    global $typenow;
    global $wp_query;

    if ($typenow=='post') {
        ob_start(); ?>
        <select name="ad_type" id="ad_type" >
			<option value="" >All Ads</option>
			<option <?php echo (isset($_GET['ad_type']) && $_GET['ad_type'] == 'single_ad') ? 'selected' : '' ; ?> value="single_ad" >Single Ads</option>
			<option <?php echo (isset($_GET['ad_type']) && $_GET['ad_type'] == 'bulk_ad') ? 'selected' : '' ; ?> value="bulk_ad" >Bulk Ads</option>
		</select>
        <?php echo ob_get_clean();
    }
}

add_filter('parse_query','convert_business_id_to_taxonomy_term_in_query');
function convert_business_id_to_taxonomy_term_in_query($query) {
    global $pagenow;
    $qv = &$query->query_vars;
	        $GLOBALS['wp']->add_query_var( 'post_parent' );

    if (is_admin() && $pagenow=='edit.php' &&  $qv['post_type'] == 'post') {
    	if (isset($_GET['ad_type']) && $_GET['ad_type'] == 'bulk_ad' ) {
	    	$qv['meta_key']     = 'bulk_id';
	        $qv['meta_compare'] = 'EXISTS';
    	} else if (isset($_GET['ad_type']) && $_GET['ad_type'] == 'single_ad' ) {
	    	$qv['meta_key']     = 'bulk_id';
	        $qv['meta_compare'] = 'NOT EXISTS';
    	}
    }
    return $query;
}
*/
