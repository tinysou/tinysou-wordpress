<?php
$api_key = get_option( 'tinysou_api_key' );
$engine_name = get_option( 'tinysou_engine_name' );
?>

<div class="wrap">

	<h2 class="tinysou-header">微搜索插件</h2>

	<p><b>管理你的搜索引擎，请访问 <a href="http://dashboard.tinysou.com/users/sign_in" target="_new">微搜索控制台</a></b>.</p>

	<table class="widefat" style="width: 650px;">
		<thead>
			<tr>
				<th class="row-title" colspan="2">微搜索插件信息</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>API Key:</td>
				<td><?php print( $api_key ); ?></td>
			</tr>
			<tr>
				<td>Engine名称:</td>
				<td><?php print( $engine_name ); ?></td>
			</tr>
			<tr>
				<td>可搜索文档数:</td>
				<td><span id="num_indexed_documents"><?php print( $num_indexed_documents ); ?></span></td>
			</tr>
		</tbody>
	</table>
	<br/>

	<?php if ( $num_indexed_documents == 0 ) : ?>
		<p>
			<b>提示信息:</b> 目前可搜索文章数为零，请先点击下面的按钮把文章提交到微搜索服务器！
		</p>
	<?php endif; ?>

	<div id="synchronizing">
		<a href="#" id="index_posts_button" class="gray-button">提交文章到微搜索服务器</a>
		<div class="swiftype" id="progress_bar" style="display: none;">
			<div class="progress">
				<div class="bar" style="display: none;"></div>
			</div>
		</div>
		<?php if ( $num_indexed_documents > 0 ) : ?>
			<p>
				<i>
				Synchronizing your posts with Swiftype ensures that your search engine has indexed all the content you have published.<br/>
				It shouldn't be necessary to synchronize posts regularly (the update process is automated after your initial setup), but<br/>
				you may use this feature any time you suspect your search index is out of date.
				</i>
			</p>
		<?php endif; ?>
	</div>

	<div id="synchronize_error" style="display: none; color: red;">
		<b>There was an error during synchronization.</b><br/>
		If this problem persists, please email support@swiftype.com and include any error message shown in the text box below, as well as the information listed in the Swiftype Search Plugin Settings box above.</b><br/>
		<textarea id="error_text" style="width: 500px; height: 200px; margin-top: 20px;"></textarea>
	</div>

	<br/>
	<hr/>
	<p>
		如果需要重新配置你的Tinysou，请点击下面按钮。
	</p>
	<form name="tinysou_settings" method="post" action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ); ?>">
		<?php wp_nonce_field('tinysou-nonce'); ?>
		<input type="hidden" name="action" value="tinysou_clear_config">
		<input type="submit" name="Submit" value="清除Tinysou配置信息"  class="button-primary" />
	</form>

</div>