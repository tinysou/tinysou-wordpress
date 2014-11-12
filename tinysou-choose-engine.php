<div class="wrap">
	<h2 class="tinysou-header">微搜索插件</h2><br/>

	<form name="tinysou_settings" method="post" action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ); ?>">
		<?php wp_nonce_field('tinysou-nonce'); ?>
		<input type="hidden" name="action" value="tinysou_create_engine">
		<table class="widefat" style="width: 650px;">
			<thead>
				<tr>
					<th class="row-title">配置 Engine</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<br/>
						请输入 Engine 名称:<br/>
						<ul>
							<li>
								<label>Engine 名称:</label>

								<input type="text" name="engine_name" class="regular-text" placeholder="engine name 至少两个字符且必须是英文字符" />

								<span class="description">示例 Wordpress Site Search</span>
							</li>
							<br/>
							<input type="submit" name="Submit" value="创建 Engine"  class="button-primary" id="create_engine"/>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
	<br/>

	<hr/>
	<p>
		如果需要重新配置你的tinysou，请点击下面按钮。
	</p>
	<form name="tinysou_settings" method="post" action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ); ?>">
		<?php wp_nonce_field('tinysou-nonce'); ?>
		<input type="hidden" name="action" value="tinysou_clear_config">
		<input type="submit" name="Submit" value="清除Tinysou配置信息"  class="button-primary" />
	</form>

</div>
