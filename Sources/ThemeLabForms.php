<?php
/**
 * Theme Lab Forms
 *
 * Form Controls Studio project functions.
 *
 * @package ThemeLab
 * @version 1.0.2
 */
if (!defined('SMF')) die('No direct access...');

function themelab_get_default_forms()
{
	return array(
		'field_size' => 'standard', 'border_radius' => 4, 'border_width' => 1,
		'padding_horizontal' => 9, 'focus_style' => 'glow',
		'textarea_height' => 82, 'textarea_resize' => 'vertical',
		'choice_size' => 16, 'choice_style' => 'classic',
		'toggle_size' => 22, 'toggle_style' => 'pill',
		'label_weight' => '500', 'required_marker' => 'asterisk',
		'validation_style' => 'border',
	);
}
function themelab_get_project_forms($project)
{
	$d=themelab_get_default_forms();
	if (empty($project['project_data_array']['forms']) || !is_array($project['project_data_array']['forms'])) return $d;
	return array_merge($d,$project['project_data_array']['forms']);
}
function themelab_validate_forms($s)
{
	$d=themelab_get_default_forms(); $e=array(); $f=array();
	$f['field_size']=isset($s['field_size'])&&in_array($s['field_size'],array('compact','standard','comfortable'),true)?$s['field_size']:$d['field_size'];
	foreach (array('border_radius'=>array(0,30),'border_width'=>array(0,5),'padding_horizontal'=>array(4,24),'textarea_height'=>array(50,240),'choice_size'=>array(12,28),'toggle_size'=>array(18,36)) as $k=>$r) {
		$f[$k]=isset($s[$k])?(int)$s[$k]:$d[$k]; if($f[$k]<$r[0]||$f[$k]>$r[1]) $e[]='themelab_forms_error_'.$k;
	}
	$allowed=array('focus_style'=>array('none','border','glow','underline'),'textarea_resize'=>array('none','vertical','horizontal','both'),'choice_style'=>array('classic','rounded','modern'),'toggle_style'=>array('pill','rounded','square'),'label_weight'=>array_keys(themelab_get_font_weights()),'required_marker'=>array('asterisk','bold','none'),'validation_style'=>array('border','background','icon'));
	foreach($allowed as $k=>$v) $f[$k]=isset($s[$k])&&in_array((string)$s[$k],$v,true)?(string)$s[$k]:$d[$k];
	$f['label_weight'] = themelab_normalize_font_weight($s['label_weight'] ?? '', $d['label_weight']);
	return array('forms'=>$f,'errors'=>array_values(array_unique($e)));
}
function themelab_save_forms($id,$forms)
{
	$p=themelab_get_project_by_id($id); if(empty($p)) return false;
	$d=!empty($p['project_data_array'])&&is_array($p['project_data_array'])?$p['project_data_array']:array();
	if(empty($d['schema'])) $d['schema']=1; $d['forms']=$forms; themelab_save_project_data($id,$d); return true;
}
function themelab_handle_form_actions()
{
	global $context; if(empty($_POST['themelab_save_forms'])) return; checkSession();
	$id=themelab_get_active_project_id(); if(empty($id)) redirectexit('action=admin;area=themelab;sa=project');
	$map=array('field_size','border_radius','border_width','padding_horizontal','focus_style','textarea_height','textarea_resize','choice_size','choice_style','toggle_size','toggle_style','label_weight','required_marker','validation_style'); $s=array();
	foreach($map as $k) $s[$k]=$_POST['themelab_forms_'.$k]??'';
	$r=themelab_validate_forms($s); $context['themelab_forms']=$r['forms']; $context['themelab_forms_errors']=$r['errors'];
	if(!empty($r['errors'])) return; if(!themelab_save_forms($id,$r['forms'])) {$context['themelab_forms_errors'][]='themelab_forms_error_save'; return;}
	redirectexit('action=admin;area=themelab;sa=forms;forms_saved');
}
