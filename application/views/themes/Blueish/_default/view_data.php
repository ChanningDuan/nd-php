<?php
/*
 * This file is part of ND PHP Framework.
 *
 * ND PHP Framework - An handy PHP Framework (www.nd-php.org)
 * Copyright (C) 2015-2016  Pedro A. Hortas (pah@ucodev.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/*
 * ND PHP Framework (www.nd-php.org) - Contributor Agreement
 *
 * When contributing material to this project, please read, accept, sign
 * and send us the Contributor Agreement located in the file NDCA.pdf
 * inside the documentation/ directory.
 *
 */

/*
 *
 *
 *  +----------------------+
 *  | Variable information |
 *  +----------------------+
 *
 *
 *  +----------------+---------------+-------------------------------------------------------+
 *  | Variable Name  | Type          | Description                                           |
 *  +----------------+---------------+-------------------------------------------------------+
 *  | $config        | array() assoc | Configuration data: Charset, theme, features, ...     |
 *  | $view          | array() assoc | View data: Field meta data, values, ...               |
 *  | $project       | array() assoc | Project information: Name, Tagline, Description, ...  |
 *  | $session       | array() assoc | Session data: Contains all session K/V pairs, ...     |
 *  | $security      | array() assoc | Security information: Role access, user info, ...     |
 *  +----------------+---------------+-------------------------------------------------------+
 *
 *  - Use the browser's 'D' access key in any ND PHP Framework page to access extended documentation.
 *  
 */

 ?>
<div id="view" class="view">
	<?php $tabs_view = true; include('lib/tabs_header.php'); ?>

 	<?php $choices_view = true; include('lib/choices.php'); ?>

	<?php $row = array_values($view['result_array'])[0]; ?>

	<div class="fields">
		<!-- Begin of basic fields -->
		<div id="fields_basic" class="fields_basic">
			<fieldset class="fields_basic_fieldset">
				<legend class="fields_basic_legend">
					<?=filter_html(ucfirst($view['hname']), $config['charset'])?>
				</legend>

				<table class="fields">
					<tr class="fields">
						<th class="fields"><?=filter_html(NDPHP_LANG_MOD_COMMON_CRUD_TITLE_FIELD_NAME, $config['charset'])?></th>
						<th class="fields"><?=filter_html(NDPHP_LANG_MOD_COMMON_CRUD_TITLE_FIELD_VALUE, $config['charset'])?></th>
					</tr>
				<?php
					$i = 0;
					foreach ($row as $field => $value):
						/* Ignore fields without meta data */
						if (!isset($view['fields'][$field]))
							continue;

						/* If this is a separator, we need to close the current table, fieldset and div and create new ones */
						if ($view['fields'][$field]['type'] == 'separator'):
				?>
				</table>
			</fieldset>
		</div>
		<div id="fields_<?=filter_html_special($field, $config['charset'])?>_container">
			<fieldset class="fields_basic_fieldset">
				<legend class="fields_basic_legend">
					<?=filter_html(ucfirst($view['fields'][$field]['viewname']), $config['charset'])?>
				</legend>

				<table class="fields">
					<tr class="fields">
						<th class="fields"><?=filter_html(NDPHP_LANG_MOD_COMMON_CRUD_TITLE_FIELD_NAME, $config['charset'])?></th>
						<th class="fields"><?=filter_html(NDPHP_LANG_MOD_COMMON_CRUD_TITLE_FIELD_VALUE, $config['charset'])?></th>
					</tr>
				<?php
							$i = 0;
							continue;
						endif;
				?>
						<tr id="<?=filter_html_special($field, $config['charset'])?>_row" class="field_<?php echo($i % 2 ? 'even' : 'odd'); ?>">
							<td class="field_name"><?=filter_html(ucfirst($view['fields'][$field]['viewname']), $config['charset'])?></td>
							<td class="field_value">
						<?php
							if ($view['fields'][$field]['input_type'] == 'checkbox') {
								echo($value == 1 ? filter_html(NDPHP_LANG_MOD_STATUS_CHECKBOX_CHECKED, $config['charset']) : filter_html(NDPHP_LANG_MOD_STATUS_CHECKBOX_UNCHECKED, $config['charset']));
							} else if ($view['fields'][$field]['input_type'] == 'textarea') {
								if (isset($config['modalbox']) && in_array($field, $config['rich_text'])) {
						?>
									<a href="<?=filter_html(base_url(), $config['charset'])?>index.php/<?=filter_html($view['ctrl'], $config['charset'])?>/view/<?=filter_html($view['id'], $config['charset'])?>" onclick="ndphp.ajax.load_body_view_frommodal(event, '<?=filter_html_js_str($view['ctrl'], $config['charset'])?>', <?=filter_html_js_special($view['id'], $config['charset'])?>);" title="<?=filter_html(NDPHP_LANG_MOD_OP_CONTEXT_VIEW, $config['charset'])?> <?=filter_html(ucfirst($view['fields'][$field]['viewname']), $config['charset'])?>" class="context_menu_link">
										<?=filter_html(NDPHP_LANG_MOD_OP_CONTEXT_VIEW, $config['charset'])?> <?=filter_html(ucfirst($view['fields'][$field]['viewname']), $config['charset'])?>
									</a>
							<?php } else { ?>
									<?php if (in_array($field, $config['rich_text'])): ?>
										<script type="text/javascript">
											tinyMCE.init({
												selector: '#<?=filter_js_special($field, $config['charset'])?>',
												mode : "textareas",
												theme : "advanced",
												readonly: true
											});
										</script>
									<?php endif; ?>
									<textarea id="<?=filter_html_special($field, $config['charset'])?>" name="<?=filter_html($field, $config['charset'])?>"><?=filter_html($value, $config['charset'])?></textarea>
							<?php } ?>
						<?php
							} else if ($view['fields'][$field]['input_type'] == 'select') {
								foreach ($view['fields'][$field]['options'] as $opt_id => $opt_value):
									if ($opt_id == $value):
						?>
										<a href="<?=filter_html(base_url(), $config['charset'])?>index.php/<?=filter_html($view['fields'][$field]['table'], $config['charset'])?>/view_data_modalbox/<?=filter_html($opt_id, $config['charset'])?>" title="<?=filter_html(NDPHP_LANG_MOD_OP_QUICK_VIEW, $config['charset'])?>" onclick="Modalbox.show(this.href, {title: this.title, width: 600}); return false;">
											<?=filter_html($opt_value, $config['charset'])?>
										</a>
						<?php
										/* The following hidden input is used to retrieve the current field opt id in order to correctly process
										 * conditional choices on selected_<?=$view['ctrl']?>_remove_choice_<?=$rel_field?>() JavaScript Function.
										 */ 
						?>
										<input type="hidden" id="<?=filter_html_special($field, $config['charset'])?>" value="<?=filter_html($opt_id, $config['charset'])?>" />
						<?php
									endif;
								endforeach;
							} else if ($view['fields'][$field]['input_type'] == 'file') {
						?>
								<a target="_blank" title="<?=filter_html($value, $config['charset'])?>" href="<?=filter_html(base_url(), $config['charset'])?>index.php/files/access/<?=filter_html($view['ctrl'], $config['charset'])?>/<?=filter_html($view['id'], $config['charset'])?>/<?=filter_html($field, $config['charset'])?>/<?=filter_html($value, $config['charset'])?>">
									<?php if ($config['render']['images'] && in_array(end(explode('.', $value)), $config['render']['ext'])): ?>
										<img alt="<?=filter_html($value, $config['charset'])?>" style="width: <?=filter_html($config['render']['size']['width'], $config['charset'])?>; height: <?=filter_html($config['render']['size']['height'], $config['charset'])?>;" src="<?=filter_html(base_url(), $config['charset'])?>index.php/files/access/<?=filter_html($view['ctrl'], $config['charset'])?>/<?=filter_html($view['id'], $config['charset'])?>/<?=filter_html($field, $config['charset'])?>/<?=filter_html($value, $config['charset'])?>" />
									<?php else: ?>
										<?=filter_html($value, $config['charset'])?>
									<?php endif; ?>
								</a>
						<?php
							} else {
								if ($field == 'id') {
									if (isset($config['modalbox'])):
						?>
										<a href="<?=filter_html(base_url(), $config['charset'])?>index.php/<?=filter_html($view['ctrl'], $config['charset'])?>/view/<?=filter_html($value, $config['charset'])?>" onclick="ndphp.ajax.load_body_view_frommodal(event, '<?=filter_html_js_str($view['ctrl'], $config['charset'])?>', <?=filter_html_js_special($value, $config['charset'])?>);" title="<?=filter_html(NDPHP_LANG_MOD_OP_CONTEXT_VIEW, $config['charset'])?> <?=filter_html($value, $config['charset'])?>">
											<?=filter_html($value, $config['charset'])?>
										</a>
						<?php
									else:
						?>
										<a href="<?=filter_html(base_url(), $config['charset'])?>index.php/<?=filter_html($view['ctrl'], $config['charset'])?>/view/<?=filter_html($value, $config['charset'])?>" onclick="ndphp.ajax.load_body_view(event, '<?=filter_html_js_str($view['ctrl'], $config['charset'])?>', <?=filter_html_js_special($value, $config['charset'])?>);" title="<?=filter_html(NDPHP_LANG_MOD_OP_CONTEXT_VIEW, $config['charset'])?> <?=filter_html($value, $config['charset'])?>">
											<?=filter_html($value, $config['charset'])?>
										</a>
						<?php
									endif;
								} else {
									echo(truncate_str($value, $config['truncate']['length'], $config['charset'], $config['truncate']['trail'], $config['truncate']['separator']));
								}
							}
						?>
							</td>
						</tr>
				<?php
						$i ++;
					endforeach;
				?>
				</table>
			</fieldset>
		</div>
		<!-- End of basic fields -->
		<!-- Begin of Multiple relationships -->
		<div id="multiple_relationships">
			<?php include('lib/multiple_view.php'); ?>
		</div>
		<!-- End of Multiple relationships -->
		<!-- Begin of Mixed relationships -->
		<div id="mixed_relationships">
			<?php include('lib/mixed_view.php'); ?>
		</div>
		<!-- End of Mixed relationships -->
		<!-- Start of Charts -->
		<div id="charts">
			<?php if ($config['charts']['total']): ?>
				<?php include('lib/charts_foreign.php'); ?>
			<?php else: ?>
				<p class="no_charts"><?=filter_html(NDPHP_LANG_MOD_EMPTY_CHARTS, $config['charset'])?></p>
			<?php endif; ?>
		</div>
		<!-- End of Charts -->
		<div class="view_ops">
			<?php if (isset($config['modalbox'])): ?>
				<a href="<?=filter_html(base_url(), $config['charset'])?>index.php/<?=filter_html($view['ctrl'], $config['charset'])?>/view/<?=filter_html($view['id'], $config['charset'])?>/pdf" title="<?=filter_html(NDPHP_LANG_MOD_OP_CONTEXT_EXPORT_PDF, $config['charset'])?>" class="context_menu_link">
					<?=filter_html(NDPHP_LANG_MOD_OP_CONTEXT_EXPORT_PDF, $config['charset'])?>
				</a>
				<a href="<?=filter_html(base_url(), $config['charset'])?>index.php/<?=filter_html($view['ctrl'], $config['charset'])?>/view/<?=filter_html($view['id'], $config['charset'])?>" onclick="ndphp.ajax.load_body_view_frommodal(event, '<?=filter_html_js_str($view['ctrl'], $config['charset'])?>', <?=filter_html_js_special($view['id'], $config['charset'])?>);" title="<?=filter_html(NDPHP_LANG_MOD_OP_CONTEXT_EXPAND, $config['charset'])?>" class="context_menu_link">
					<?=filter_html(NDPHP_LANG_MOD_OP_CONTEXT_EXPAND, $config['charset'])?>
				</a>
				<script type="text/javascript">
					jQuery('#MB_content div.view').css('width', '100%');
				</script>
			<?php endif; ?>
		</div>
	</div>
	<?php include('lib/tabs_footer.php'); ?>
</div>
