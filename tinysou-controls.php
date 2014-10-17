<?php
$nonce = wp_create_nonce( 'tinysou-ajax-nonce' );
$api_key = get_option( 'tinysou_api_key' );
$engine_name = get_option( 'tinysou_engine_name' );
$tinysou_seaechable_num = get_option( 'tinysou_searchable_num' );
$count_posts = wp_count_posts();
$published_posts = $count_posts->publish;

$allowed_post_types = array( 'post', 'page' );
if ( function_exists( 'get_post_types' ) ) {
	$allowed_post_types = array_merge( get_post_types( array( 'exclude_from_search' => '0' ) ), get_post_types( array( 'exclude_from_search' => false ) ) );
}
$total_posts = 0;
$total_posts_in_trash = 0;

foreach( $allowed_post_types as $type ) {
	$type_count = wp_count_posts($type);
	foreach( $type_count as $status => $count) {
		if( 'publish' == $status ) {
			$total_posts += $count;
		} else {
			$total_posts_in_trash += $count;
		}
	}
}
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
				<td>已发布文章数目:</td>
				<td><?php print( $published_posts ); ?></td>
			</tr>
			<tr>
				<td>已提交文章数目:</td>
				<td><?php print( $tinysou_seaechable_num ); ?></td>
			</tr>
			<!-- <tr>
				<td>可搜索文档数:</td>
				<td><span id="num_indexed_documents"><?php //print( $num_indexed_documents ); ?></span></td>
			</tr> -->
		</tbody>
	</table>
	<br/>

	<?php //if ( $num_indexed_documents == 0 ) : ?>
		<!-- <p>
			<b>提示信息:</b> 目前可搜索文章数为零，请先点击下面的按钮把文章提交到微搜索服务器！
		</p> -->
	<?php //endif; ?>

	<div id="synchronizing">
		<a href="javascript:void(0);" id="index_posts_button" class="gray-button">提交文章到微搜索服务器</a>
		<div class="tinysou" id="progress_bar" style="display: none;">
			<div class="progress">
				<div class="bar" style="display: none;"></div>
			</div>
		</div>
		<?php //if ( $num_indexed_documents > 0 ) : ?>
			<!-- <p>
				<i>
				Synchronizing your posts with Swiftype ensures that your search engine has indexed all the content you have published.<br/>
				It shouldn't be necessary to synchronize posts regularly (the update process is automated after your initial setup), but<br/>
				you may use this feature any time you suspect your search index is out of date.
				</i>
			</p> -->
		<?php //endif; ?>
	</div>

	<div id="synchronize_error" style="display: none; color: red;">
		<b>提交文档出错</b><br/>
		<b>请检查你的网络，或者联系微搜索管理员!</b><br/>
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

<script>
	jQuery('#index_posts_button').click(function() {
		index_batch_of_posts(0);
	});

	var batch_size = 15;

	var total_posts_written = 0;
	var total_posts_processed = 0;
	var total_posts = <?php print( $total_posts ) ?>;

	var index_batch_of_posts = function(start) {
		set_progress();
		var offset = start || 0;
		var data = { action: 'sync_posts', offset: offset, batch_size: batch_size, _ajax_nonce: '<?php echo $nonce ?>' };
		jQuery.ajax({
				url: ajaxurl,
				data: data,
				dataType: 'text',
				type: 'POST',
				success: function(response, textStatus) {
					console.log(response);
					var increment = response['num_written'];
					if (increment) {
						total_posts_written += increment;
					}
					total_posts_processed += batch_size;
					if (response['total'] > 0) {
						index_batch_of_posts(offset + batch_size);
					} else {
						total_posts_processed = total_posts;
						set_progress();
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					//console.log(errorThrown);
					try {
						errorMsg = JSON.parse(jqXHR.responseText).message;
					} catch (e) {
						errorMsg = jqXHR.responseText;
						show_error(errorMsg);
					}
				}
			}
		);
	};

	// var total_posts_in_trash_processed = 0;
	// var total_posts_in_trash = <?php //print( $total_posts_in_trash ) ?>;
	// var delete_batch_of_posts = function(start) {
	// 	set_progress();
	// 	var offset = start || 0;
	// 	var data = { action: 'delete_batch_of_trashed_posts', offset: offset, batch_size: batch_size, _ajax_nonce: '<?php echo $nonce ?>' };
	// 	jQuery.ajax({
	// 			url: ajaxurl,
	// 			data: data,
	// 			dataType: 'json',
	// 			type: 'POST',
	// 			success: function(response, textStatus) {
	// 				console.log(response);
	// 				total_posts_in_trash_processed += batch_size;
	// 				if (response['total'] > 0) {
	// 					delete_batch_of_posts(offset + batch_size);
	// 				} else {
	// 					total_posts_in_trash_processed = total_posts_in_trash;
	// 					set_progress();
	// 				}
	// 			},
	// 			error: function(jqXHR, textStatus, errorThrown) {
	// 				try {
	// 					errorMsg = JSON.parse(jqXHR.responseText).message;
	// 				} catch (e) {
	// 					errorMsg = jqXHR.responseText;
	// 					show_error(errorMsg);
	// 				}
	// 			}
	// 		}
	// 	);
	// };

	function refresh_num_indexed_documents() {
		jQuery.ajax({
				url: ajaxurl,
				data: { action: 'refresh_num_indexed_documents', _ajax_nonce: '<?php echo $nonce ?>' },
				dataType: 'json',
				type: 'GET',
				success: function(response, textStatus) {
					return;
				},
				error: function(jqXHR, textStatus, errorThrown) {
					try {
						errorMsg = JSON.parse(jqXHR.responseText).message;
						show_error(errorMsg);
					} catch (e) {
						errorMsg = jqXHR.responseText;
						show_error(errorMsg);
					}
				}
			}
		);
	}

	function show_error(message) {
		jQuery('#synchronizing').fadeOut();
		jQuery('#synchronize_error').fadeIn();
		if(message.length > 0) {
			jQuery('#error_text').append(message).show();
		}
	}

	function set_progress() {
		var total_ops = total_posts;
		var progress = total_posts_processed;
		// console.log(total_ops);
		// console.log(progress);
		if(progress > total_ops) { progress = total_ops; }
		var progress_width = Math.round(progress / total_ops * 245);
		if(progress_width < 10) { progress_width = 10; }
		if(progress == 0) {
			jQuery('#progress_bar').fadeIn();
		}
		jQuery('#num_indexed_documents').html(total_posts_written);
		jQuery('#progress_bar').find('div.bar').show().width(progress_width);
		if(progress >= total_ops) {
			refresh_num_indexed_documents();
			jQuery('#index_posts_button').html('提交完成!');
			jQuery('#progress_bar').fadeOut();
			jQuery('#index_posts_button').unbind();
		} else {
			jQuery('#index_posts_button').html('正在提交... ' + Math.round(progress / total_ops * 100) + '%');
		}
	}

</script>