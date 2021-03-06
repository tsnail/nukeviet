<?php

/**
 * @Project NUKEVIET 3.x
 * @Author VINADES (contact@vinades.vn)
 * @Copyright ? 2010 VINADES. All rights reserved
 * @Createdate 04/05/2010
 */

if( ! defined( 'NV_IS_FILE_ADMIN' ) ) die( 'Stop!!!' );

$page_title = $lang_module['edit_title'];

$userid = $nv_Request->get_int( 'userid', 'get', 0 );

if( empty( $userid ) )
{
	Header( "Location: " . NV_BASE_ADMINURL . "index.php?" . NV_NAME_VARIABLE . "=" . $module_name );
	die();
}

$sql = "SELECT * FROM `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "` WHERE `userid`=" . $userid;
$result = $db->sql_query( $sql );
$numrows = $db->sql_numrows( $result );
if( $numrows != 1 )
{
	Header( "Location: " . NV_BASE_ADMINURL . "index.php?" . NV_NAME_VARIABLE . "=" . $module_name );
	die();
}
$row = $db->sql_fetchrow( $result );

$allow = false;

$sql = "SELECT `lev` FROM `" . NV_AUTHORS_GLOBALTABLE . "` WHERE `admin_id`=" . $userid;
$query = $db->sql_query( $sql );
$numrows = $db->sql_numrows( $query );
if( ! $numrows )
{
	$allow = true;
}
else
{
	list( $level ) = $db->sql_fetchrow( $query );

	if( $admin_info['admin_id'] == $userid or $admin_info['level'] < $level )
	{
		$allow = true;
	}
}

if( $global_config['idsite'] > 0 AND $row['idsite'] != $global_config['idsite'] AND $admin_info['admin_id'] != $userid )
{
	$allow = false;
}

if( ! $allow )
{
	Header( "Location: " . NV_BASE_ADMINURL . "index.php?" . NV_NAME_VARIABLE . "=" . $module_name );
	die();
}

$_user = array();

$groups_list = nv_groups_list();

$array_old_groups = array();
$result_gru = $db->sql_query( "SELECT `group_id` FROM `" . $db_config['dbsystem'] . "`.`" . NV_GROUPS_GLOBALTABLE . "_users` WHERE `userid`=" . $userid );
while( $row_gru = $db->sql_fetch_assoc( $result_gru ) )
{
	$array_old_groups[] = $row_gru['group_id'];
}

$array_field_config = array();
$result_field = $db->sql_query( "SELECT * FROM `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "_field` ORDER BY `weight` ASC" );
while( $row_field = $db->sql_fetch_assoc( $result_field ) )
{
	$language = unserialize( $row_field['language'] );
	$row_field['title'] = ( isset( $language[NV_LANG_DATA] ) ) ? $language[NV_LANG_DATA][0] : $row['field'];
	$row_field['description'] = ( isset( $language[NV_LANG_DATA] ) ) ? nv_htmlspecialchars( $language[NV_LANG_DATA][1] ) : '';
	if( ! empty( $row_field['field_choices'] ) ) $row_field['field_choices'] = unserialize( $row_field['field_choices'] );
	elseif( ! empty( $row_field['sql_choices'] ) )
	{
		$row_field['sql_choices'] = explode( "|", $row_field['sql_choices'] );
		$query = "SELECT `" . $row_field['sql_choices'][2] . "`, `" . $row_field['sql_choices'][3] . "` FROM `" . $row_field['sql_choices'][1] . "`";
		$result = $db->sql_query( $query );
		$weight = 0;
		while( list( $key, $val ) = $db->sql_fetchrow( $result ) )
		{
			$row_field['field_choices'][$key] = $val;
		}
	}
	$array_field_config[] = $row_field;
}

if( defined( 'NV_EDITOR' ) )
{
	require_once ( NV_ROOTDIR . '/' . NV_EDITORSDIR . '/' . NV_EDITOR . '/nv.php' );
}

$error = "";
$access_passus = ( isset( $access_admin['access_passus'][$admin_info['level']] ) and $access_admin['access_passus'][$admin_info['level']] == 1 ) ? true : false;

if( $nv_Request->isset_request( 'confirm', 'post' ) )
{
	$_user['username'] = filter_text_input( 'username', 'post', '', 1, NV_UNICKMAX );
	$_user['email'] = filter_text_input( 'email', 'post', '', 1, 100 );
	if( $access_passus )
	{
		$_user['password1'] = filter_text_input( 'password1', 'post', '', 0, NV_UPASSMAX );
		$_user['password2'] = filter_text_input( 'password2', 'post', '', 0, NV_UPASSMAX );
	}
	else
	{
		$_user['password1'] = $_user['password2'] = '';
	}
	$_user['question'] = filter_text_input( 'question', 'post', '', 1, 255 );
	$_user['answer'] = filter_text_input( 'answer', 'post', '', 1, 255 );
	$_user['full_name'] = filter_text_input( 'full_name', 'post', '', 1, 255 );
	$_user['gender'] = filter_text_input( 'gender', 'post', '', 1, 1 );
	$_user['view_mail'] = $nv_Request->get_int( 'view_mail', 'post', 0 );
	$_user['sig'] = filter_text_textarea( 'sig', '', NV_ALLOWED_HTML_TAGS );
	$_user['birthday'] = filter_text_input( 'birthday', 'post', '', 1, 10 );
	$_user['in_groups'] = $nv_Request->get_typed_array( 'group', 'post', 'int' );
	$_user['delpic'] = $nv_Request->get_int( 'delpic', 'post', 0 );

	$custom_fields = $nv_Request->get_array( 'custom_fields', 'post' );

	if( $_user['username'] != $row['username'] and ( $error_username = nv_check_valid_login( $_user['username'], NV_UNICKMAX, NV_UNICKMIN ) ) != "" )
	{
		$error = $error_username;
	}
	elseif( $_user['username'] != $db->fixdb( $_user['username'] ) )
	{
		$error = sprintf( $lang_module['account_deny_name'], '<strong>' . $_user['username'] . '</strong>' );
	}
	elseif( ( $error_xemail = nv_check_valid_email( $_user['email'] ) ) != "" )
	{
		$error = $error_xemail;
	}
	elseif( $db->sql_numrows( $db->sql_query( "SELECT `userid` FROM `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "` WHERE `userid`!=" . $userid . " AND `md5username`=" . $db->dbescape( nv_md5safe( $_user['username'] ) ) ) ) != 0 )
	{
		$error = $lang_module['edit_error_username_exist'];
	}
	elseif( $db->sql_numrows( $db->sql_query( "SELECT `userid` FROM `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "` WHERE `userid`!=" . $userid . " AND `email`=" . $db->dbescape( $_user['email'] ) ) ) != 0 )
	{
		$error = $lang_module['edit_error_email_exist'];
	}
	elseif( $db->sql_numrows( $db->sql_query( "SELECT `userid` FROM `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "_reg` WHERE `email`=" . $db->dbescape( $_user['email'] ) ) ) != 0 )
	{
		$error = $lang_module['edit_error_email_exist'];
	}
	elseif( $db->sql_numrows( $db->sql_query( "SELECT `userid` FROM `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "_openid` WHERE `userid`!=" . $userid . " AND `email`=" . $db->dbescape( $_user['email'] ) ) ) != 0 )
	{
		$error = $lang_module['edit_error_email_exist'];
	}
	elseif( ! empty( $_user['password1'] ) and ( $check_pass = nv_check_valid_pass( $_user['password1'], NV_UPASSMAX, NV_UPASSMIN ) ) != "" )
	{
		$error = $check_pass;
	}
	elseif( ! empty( $_user['password1'] ) and $_user['password1'] != $_user['password2'] )
	{
		$error = $lang_module['edit_error_password'];
	}
	elseif( empty( $_user['question'] ) )
	{
		$error = $lang_module['edit_error_question'];
	}
	elseif( empty( $_user['answer'] ) )
	{
		$error = $lang_module['edit_error_answer'];
	}
	else
	{
		$query_field = array();
		if( ! empty( $array_field_config ) )
		{
			require ( NV_ROOTDIR . "/modules/users/fields.check.php" );
		}

		if( empty( $error ) )
		{
			$_user['sig'] = nv_nl2br( $_user['sig'], "<br />" );
			if( $_user['gender'] != "M" and $_user['gender'] != "F" )
			{
				$_user['gender'] = "";
			}

			if( preg_match( "/^([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{4})$/", $_user['birthday'], $m ) )
			{
				$_user['birthday'] = mktime( 0, 0, 0, $m[2], $m[1], $m[3] );
			}
			else
			{
				$_user['birthday'] = 0;
			}

			$password = ! empty( $_user['password1'] ) ? $crypt->hash( $_user['password1'] ) : $row['password'];

			$photo = $row['photo'];
			if( $_user['delpic'] )
			{
				if( ! empty( $photo ) and is_file( NV_ROOTDIR . '/' . $photo ) )
				{
					if( nv_deletefile( NV_ROOTDIR . '/' . $photo ) )
					{
						$photo = "";
					}
				}
			}

			$in_groups = array_intersect( $_user['in_groups'], array_keys( $groups_list ) );
			$in_groups_hiden = array_diff( $array_old_groups, array_keys( $groups_list ) );
			$in_groups = array_unique( array_merge( $in_groups, $in_groups_hiden ) );

			$in_groups_del = array_diff( $array_old_groups, $in_groups );
			if( ! empty( $in_groups_del ) )
			{
				foreach( $in_groups_del as $gid )
				{
					nv_groups_del_user( $gid, $userid );
				}
			}

			$in_groups_add = array_diff( $in_groups, $array_old_groups );
			if( ! empty( $in_groups_add ) )
			{
				foreach( $in_groups_add as $gid )
				{
					nv_groups_add_user( $gid, $userid );
				}
			}

			$db->sql_query( "UPDATE `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "` SET 
				`username`=" . $db->dbescape( $_user['username'] ) . ", 
				`md5username`=" . $db->dbescape( nv_md5safe( $_user['username'] ) ) . ", 
				`password`=" . $db->dbescape( $password ) . ", 
				`email`=" . $db->dbescape( $_user['email'] ) . ", 
				`full_name`=" . $db->dbescape( $_user['full_name'] ) . ", 
				`gender`=" . $db->dbescape( $_user['gender'] ) . ", 
				`photo`=" . $db->dbescape( $photo ) . ", 
				`birthday`=" . $_user['birthday'] . ", 
				`sig`=" . $db->dbescape( $_user['sig'] ) . ", 
				`question`=" . $db->dbescape( $_user['question'] ) . ", 
				`answer`=" . $db->dbescape( $_user['answer'] ) . ", 
				`view_mail`=" . $_user['view_mail'] . ", 
				`in_groups`='".implode( ',', $in_groups )."' 
				WHERE `userid`=" . $userid );

			if( ! empty( $array_field_config ) )
			{
				$db->sql_query( "UPDATE `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "_info` SET " . implode( ', ', $query_field ) . " WHERE `userid`=" . $userid );
			}

			nv_insert_logs( NV_LANG_DATA, $module_name, 'log_edit_user', "userid " . $userid, $admin_info['userid'] );

			if( isset( $_FILES['photo'] ) and is_uploaded_file( $_FILES['photo']['tmp_name'] ) )
			{
				@require_once ( NV_ROOTDIR . "/includes/class/upload.class.php" );

				$upload = new upload( array( 'images' ), $global_config['forbid_extensions'], $global_config['forbid_mimes'], NV_UPLOAD_MAX_FILESIZE, NV_MAX_WIDTH, NV_MAX_HEIGHT );
				$upload_info = $upload->save_file( $_FILES['photo'], NV_ROOTDIR . '/' . SYSTEM_UPLOADS_DIR . '/' . $module_name, false );

				@unlink( $_FILES['photo']['tmp_name'] );
				if( empty( $upload_info['error'] ) )
				{
					require_once ( NV_ROOTDIR . "/includes/class/image.class.php" );
					$_image = new image( $upload_info['name'], 80, 80 );
					$_image->resizeXY( 80, 80 );
					$_image->save( NV_ROOTDIR . '/' . SYSTEM_UPLOADS_DIR . '/' . $module_name, $upload_info['basename'] );

					@chmod( $upload_info['name'], 0644 );
					if( ! empty( $photo ) and is_file( NV_ROOTDIR . '/' . $photo ) )
					{
						@nv_deletefile( NV_ROOTDIR . '/' . $photo );
					}

					$file_name = str_replace( NV_ROOTDIR . "/", "", $upload_info['name'] );

					$sql = "UPDATE `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "` SET `photo`=" . $db->dbescape( $file_name ) . " WHERE `userid`=" . $userid;
					$db->sql_query( $sql );
				}
			}

			Header( "Location: " . NV_BASE_ADMINURL . "index.php?" . NV_NAME_VARIABLE . "=" . $module_name );
			exit();
		}
	}
}
else
{
	$_user = $row;
	$_user['password1'] = $_user['password2'] = "";
	$_user['birthday'] = ! empty( $_user['birthday'] ) ? date( "d/m/Y", $_user['birthday'] ) : "";
	$_user['in_groups'] = $array_old_groups;
	if( ! empty( $_user['sig'] ) ) $_user['sig'] = nv_br2nl( $_user['sig'] );

	$sql = "SELECT * FROM `" . $db_config['dbsystem'] . "`.`" . NV_USERS_GLOBALTABLE . "_info` WHERE `userid`=" . $userid;
	$result = $db->sql_query( $sql );
	$custom_fields = $db->sql_fetch_assoc( $result );
}

$genders = array(
	'N' => array(
		'key' => 'N',
		'title' => $lang_module['NA'],
		'selected' => ''
	),
	'M' => array(
		'key' => 'M',
		'title' => $lang_module['male'],
		'selected' => $_user['gender'] == "M" ? " selected=\"selected\"" : ""
	),
	'F' => array(
		'key' => 'F',
		'title' => $lang_module['female'],
		'selected' => $_user['gender'] == "F" ? " selected=\"selected\"" : ""
	)
);

$_user['view_mail'] = $_user['view_mail'] ? " checked=\"checked\"" : "";

if( ! empty( $_user['sig'] ) ) $_user['sig'] = nv_htmlspecialchars( $_user['sig'] );

$groups = array();
if( ! empty( $groups_list ) )
{
	foreach( $groups_list as $group_id => $grtl )
	{
		$groups[] = array(
			'id' => $group_id,
			'title' => $grtl,
			'checked' => ( in_array( $group_id, $_user['in_groups'] ) ) ? " checked=\"checked\"" : ""
		);
	}
}

$xtpl = new XTemplate( "user_edit.tpl", NV_ROOTDIR . "/themes/" . $global_config['module_theme'] . "/modules/" . $module_file );
$xtpl->assign( 'LANG', $lang_module );
$xtpl->assign( 'DATA', $_user );
$xtpl->assign( 'FORM_ACTION', NV_BASE_ADMINURL . "index.php?" . NV_NAME_VARIABLE . "=" . $module_name . "&amp;" . NV_OP_VARIABLE . "=edit&amp;userid=" . $userid );
$xtpl->assign( 'NV_BASE_SITEURL', NV_BASE_SITEURL );
$xtpl->assign( 'NV_LANG_INTERFACE', NV_LANG_INTERFACE );

if( ! empty( $error ) )
{
	$xtpl->assign( 'ERROR', $error );
	$xtpl->parse( 'main.error' );
}

if( defined( 'NV_IS_USER_FORUM' ) )
{
	$xtpl->parse( 'main.is_forum' );
}
else
{
	foreach( $genders as $gender )
	{
		$xtpl->assign( 'GENDER', $gender );
		$xtpl->parse( 'main.edit_user.gender' );
	}

	if( ! empty( $row['photo'] ) )
	{
		$size = @getimagesize( NV_ROOTDIR . '/' . $row['photo'] );
		$img = array(
			'href' => $row['photo'],
			'height' => $size[1],
			'width' => $size[0]
		);
		$xtpl->assign( 'IMG', $img );
		$xtpl->parse( 'main.edit_user.photo' );
	}

	if( ! empty( $groups ) )
	{
		foreach( $groups as $group )
		{
			$xtpl->assign( 'GROUP', $group );
			$xtpl->parse( 'main.edit_user.group.list' );
		}
		$xtpl->parse( 'main.edit_user.group' );
	}
	if( $access_passus )
	{
		$xtpl->parse( 'main.edit_user.changepass' );
	}

	if( ! empty( $array_field_config ) )
	{
		$a = 0;
		foreach( $array_field_config as $row )
		{
			if( ( $row['show_register'] and $userid == 0 ) or $userid > 0 )
			{
				$row['tbodyclass'] = ( $a % 2 ) ? " class=\"second\"" : "";
				if( $userid == 0 and ! $nv_Request->isset_request( 'confirm', 'post' ) )
				{
					if( ! empty( $row['field_choices'] ) )
					{
						if( $row['field_type'] == 'date' )
						{
							$row['value'] = ( $row['field_choices']['current_date'] ) ? NV_CURRENTTIME : $row['default_value'];
						}
						elseif( $row['field_type'] == 'number' )
						{
							$row['value'] = $row['default_value'];
						}
						else
						{
							$temp = array_keys( $row['field_choices'] );
							$tempkey = intval( $row['default_value'] ) - 1;
							$row['value'] = ( isset( $temp[$tempkey] ) ) ? $temp[$tempkey] : '';
						}
					}
					else
					{
						$row['value'] = $row['default_value'];
					}
				}
				else
				{
					$row['value'] = ( isset( $custom_fields[$row['field']] ) ) ? $custom_fields[$row['field']] : $row['default_value'];
				}
				$row['required'] = ( $row['required'] ) ? 'required' : '';

				$xtpl->assign( 'FIELD', $row );
				if( $row['required'] )
				{
					$xtpl->parse( 'main.edit_user.field.loop.required' );
				}
				if( $row['field_type'] == 'textbox' or $row['field_type'] == 'number' )
				{
					$xtpl->parse( 'main.edit_user.field.loop.textbox' );
				}
				elseif( $row['field_type'] == 'date' )
				{
					$row['value'] = ( empty( $row['value'] ) ) ? '' : date( 'd/m/Y', $row['value'] );
					$xtpl->assign( 'FIELD', $row );
					$xtpl->parse( 'main.edit_user.field.loop.date' );
				}
				elseif( $row['field_type'] == 'textarea' )
				{
					$row['value'] = nv_htmlspecialchars( nv_br2nl( $row['value'] ) );
					$xtpl->assign( 'FIELD', $row );
					$xtpl->parse( 'main.edit_user.field.loop.textarea' );
				}
				elseif( $row['field_type'] == 'editor' )
				{
					if( defined( 'NV_EDITOR' ) and nv_function_exists( 'nv_aleditor' ) )
					{
						$row['value'] = nv_htmlspecialchars( nv_editor_br2nl( $row['value'] ) );
						$array_tmp = explode( "@", $row['class'] );
						$edits = nv_aleditor( 'custom_fields[' . $row['field'] . ']', $array_tmp[0], $array_tmp[1], $row['value'] );
						$xtpl->assign( 'EDITOR', $edits );
						$xtpl->parse( 'main.edit_user.field.loop.editor' );
					}
					else
					{
						$row['value'] = nv_htmlspecialchars( nv_br2nl( $row['value'] ) );
						$row['class'] = '';
						$xtpl->assign( 'FIELD', $row );
						$xtpl->parse( 'main.edit_user.field.loop.textarea' );
					}
				}
				elseif( $row['field_type'] == 'select' )
				{
					foreach( $row['field_choices'] as $key => $value )
					{
						$xtpl->assign( 'FIELD_CHOICES', array(
							"key" => $key,
							"selected" => ( $key == $row['value'] ) ? ' selected="selected"' : '',
							"value" => $value
						) );
						$xtpl->parse( 'main.edit_user.field.loop.select.loop' );
					}
					$xtpl->parse( 'main.edit_user.field.loop.select' );
				}
				elseif( $row['field_type'] == 'radio' )
				{
					$number = 0;
					foreach( $row['field_choices'] as $key => $value )
					{
						$xtpl->assign( 'FIELD_CHOICES', array(
							"id" => $row['fid'] . '_' . $number++,
							"key" => $key,
							"checked" => ( $key == $row['value'] ) ? ' checked="checked"' : '',
							"value" => $value
						) );
						$xtpl->parse( 'main.edit_user.field.loop.radio' );
					}
				}
				elseif( $row['field_type'] == 'checkbox' )
				{
					$number = 0;
					$valuecheckbox = ( ! empty( $row['value'] ) ) ? explode( ',', $row['value'] ) : array();
					foreach( $row['field_choices'] as $key => $value )
					{
						$xtpl->assign( 'FIELD_CHOICES', array(
							"id" => $row['fid'] . '_' . $number++,
							"key" => $key,
							"checked" => ( in_array( $key, $valuecheckbox ) ) ? ' checked="checked"' : '',
							"value" => $value
						) );
						$xtpl->parse( 'main.edit_user.field.loop.checkbox' );
					}
				}
				elseif( $row['field_type'] == 'multiselect' )
				{
					$valueselect = ( ! empty( $row['value'] ) ) ? explode( ',', $row['value'] ) : array();
					foreach( $row['field_choices'] as $key => $value )
					{
						$xtpl->assign( 'FIELD_CHOICES', array(
							"key" => $key,
							"selected" => ( in_array( $key, $valueselect ) ) ? ' selected="selected"' : '',
							"value" => $value
						) );
						$xtpl->parse( 'main.edit_user.field.loop.multiselect.loop' );
					}
					$xtpl->parse( 'main.edit_user.field.loop.multiselect' );
				}
				$xtpl->parse( 'main.edit_user.field.loop' );
			}
		}
		$xtpl->parse( 'main.edit_user.field' );
	}

	$xtpl->parse( 'main.edit_user' );
}

$xtpl->parse( 'main' );
$contents = $xtpl->text( 'main' );

include ( NV_ROOTDIR . "/includes/header.php" );
echo nv_admin_theme( $contents );
include ( NV_ROOTDIR . "/includes/footer.php" );

?>