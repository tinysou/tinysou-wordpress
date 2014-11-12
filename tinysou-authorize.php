<div class="wrap">
	<h2 class="tinysou-header">微搜索插件</h2><br/>
	<form name="tinysou_settings" method="post" action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ); ?>">
		<?php wp_nonce_field('tinysou-nonce'); ?>
		<input type="hidden" name="action" value="tinysou_set_api_key">
		<table class="widefat" style="width: 650px;">
			<thead>
				<tr>
					<th class="row-title">认证插件</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						感谢使用为搜索插件，请在下面输入您的appid
						<ul>
							<li>
								<label>Tinysou API Key:</label>
								<input type="text" name="api_key" class="regular-text" />
								<input type="submit" name="Submit" value="认证" class="button-primary" />
							</li>
					</td>
				</tr>
			</tbody>
		</table>
	</form>

</div>