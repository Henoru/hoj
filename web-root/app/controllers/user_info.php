<?php
	if($_GET['list']=='user'){ // for autocomplete user
		$list = array();
		$res = DB::query('select username,rating from user_info order by username');
		while($row=DB::fetch($res,MYSQLI_NUM)){
			$list[]=array($row[0],$row[1]);
		}
		die(json_encode($list));
	}
	
	$username = $_GET['username'];
	
	requireLib('flot');
?>

<?php if (validateUsername($username) && ($user = queryUser($username))): ?>

<?php echoUOJPageHeader($user['username'] . ' - ' . UOJLocale::get('user profile')) ?>
<?php
	$esc_email = HTML::escape($user['email']);
	$esc_qq = HTML::escape($user['qq'] != 0 ? $user['qq'] : 'Unfilled');
	$esc_sex = HTML::escape($user['sex']);
	$col_sex="color:blue";
	if($esc_sex == "M") {
		$esc_sex="♂";
		$col_sex="color:blue";
	}else if($esc_sex == "F") {
		$esc_sex="♀";
		$col_sex="color:red";
	} else {
		$esc_sex="";
		$col_sex="color:black";
	}
	$setting = json_decode($user['setting'],true);
	$today=(int)(time()/60/60/24);
	while($user['liveness_day']<$today&&$today-$user['liveness_day']<=30){
		$user['liveness']=(double)$user['liveness']*0.7;
		$user['liveness_day']++;
	}
	if($today-$user['liveness_day']>30)
		$user['liveness']=0;
?>
	
<div class="panel panel-info">
	<div class="panel-heading">
		<h2 class="panel-title"><?= UOJLocale::get('user profile') ?></h2>
	</div>
	<div class="panel-body">
		<div class="row">
			<div class="col-md-4 col-md-push-8">
				<img class="media-object img-thumbnail center-block" alt="该用户头像无法显示" src="<?= HTML::escape($setting['avatar']) ?>" />
			</div>
			<div class="col-md-8 col-md-pull-4">
				<h2><span class="uoj-honor" data-rating="<?= $user['rating'] ?>"><?= $user['username'] ?></span> <span><strong style="<?= $col_sex ?>"><?= $esc_sex ?></strong></span></h2>
				<div class="list-group">
					<div class="list-group-item">
						<h4 class="list-group-item-heading"><?= UOJLocale::get('rating') ?></h4>
						<p class="list-group-item-text"><strong style="color:red"><?= $user['rating'] ?></strong></p>
					</div>
					<div class="list-group-item">
						<h4 class="list-group-item-heading">活跃度</h4>
						<p class="list-group-item-text"><strong style="color:red"><?= (int)($user['liveness']) ?></strong></p>
					</div>
					<div class="list-group-item">
						<h4 class="list-group-item-heading">班级姓名</h4>
					<p class="list-group-item-text"><?= $esc_email ?></p>
					</div>
					<div class="list-group-item">
						<h4 class="list-group-item-heading"><?= UOJLocale::get('QQ') ?></h4>
						<p class="list-group-item-text"><?= $esc_qq ?></p>
					</div>
					<div class="list-group-item">
						<h4 class="list-group-item-heading"><?= UOJLocale::get('motto') ?></h4>
						<p class="list-group-item-text"><article><?= $user['motto'] ?></article></p>
					</div>
					<?php if (isSuperUser($myUser)): ?>
						<div class="list-group-item">
							<h4 class="list-group-item-heading">register time</h4>
							<p class="list-group-item-text"><?= $user['register_time'] ?></p>
						</div>
						<div class="list-group-item">
							<h4 class="list-group-item-heading">remote_addr</h4>
							<p class="list-group-item-text"><?= $user['remote_addr'] ?></p>
						</div>
						<div class="list-group-item">
							<h4 class="list-group-item-heading">http_x_forwarded_for</h4>
							<p class="list-group-item-text"><?= $user['http_x_forwarded_for'] ?></p>
						</div>
					<?php endif ?>
				</div>
			</div>
		</div>
		<?php if (Auth::check()): ?>
			<?php if (Auth::id() != $user['username']): ?>
				<a type="button" class="btn btn-info btn-sm" href="/user/msg?enter=<?= $user['username'] ?>"><span class="glyphicon glyphicon-envelope"></span> <?= UOJLocale::get('send private message') ?></a>
			<?php else: ?>
				<a type="button" class="btn btn-info btn-sm" href="/user/modify-profile"><span class="glyphicon glyphicon-pencil"></span> <?= UOJLocale::get('modify my profile') ?></a>
			<?php endif ?>
		<?php endif ?>
		<a type="button" class="btn btn-success btn-sm" href="<?= HTML::blog_url($user['username'], '/') ?>"><span class="glyphicon glyphicon-arrow-right"></span> <?= UOJLocale::get('visit his blog', $username) ?></a>
		<a type="button" class="btn btn-success btn-sm" href="<?= '/submissions?submitter='.$user['username'] ?>"><span class="glyphicon glyphicon-arrow-right"></span> <?= '查看'.($user['rating']>2000?'正解':(($user['sex']=='U'?'TA':($user['sex']=='F'?'她':'他')).'的提交记录')) ?></a>
		<div class="top-buffer-lg"></div>
		<div class="list-group">
			<div class="list-group-item">
				<h4 class="list-group-item-heading"><?= UOJLocale::get('rating changes') ?></h4>
				<div class="list-group-item-text" id="rating-plot" style="height:500px;"></div>
			</div>
			<div class="list-group-item">
				<?php $ac_problems = DB::selectAll("select problem_id from best_ac_submissions where submitter = '{$user['username']}'"); ?>
				<h4 class="list-group-item-heading"><?= UOJLocale::get('accepted problems').'：'.UOJLocale::get('n problems in total', count($ac_problems))?> </h4>
				<p class="list-group-item-text">
					<?php
						foreach ($ac_problems as $problem) {
							echo '<a href="/problem/', $problem['problem_id'], '" style="display:inline-block; width:4em;">', $problem['problem_id'], '</a>';
						}
						if (empty($ac_problems)) {
							echo UOJLocale::get('none');
						}
					?>
				</p>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
var rating_data = [[
<?php
	$user_rating_min = $user_rating_max = 1500;
	$result = DB::query("select contest_id, rank, user_rating from contests_registrants where username = '{$user['username']}' and has_participated = 1 order by contest_id");
	$is_first_row = true;
	$last_rating = 1500;
	while ($row = DB::fetch($result)) {
		$contest = queryContest($row['contest_id']);
		$rating_delta = $row['user_rating'] - $last_rating;
		if (!$is_first_row) {
			echo "[$last_contest_time, {$row['user_rating']}, $last_contest_id, '$last_contest_name', $last_rank, $rating_delta],";
		} else {
			$is_first_row = false;
		}
		$contest_start_time = new DateTime($contest['start_time']);
		$last_contest_time = ($contest_start_time->getTimestamp() + $contest_start_time->getOffset()) * 1000;
		$last_contest_name = $contest['name'];
		$last_contest_id = $contest['id'];
		$last_rank = $row['rank'];
		$last_rating = $row['user_rating'];
		
		if ($row['user_rating'] < $user_rating_min) {
			$user_rating_min = $row['user_rating'];
		}
		if ($row['user_rating'] > $user_rating_max) {
			$user_rating_max = $row['user_rating'];
		}
	}
	if ($is_first_row) {
		$time_now_stamp = (UOJTime::$time_now->getTimestamp() + UOJTime::$time_now->getOffset()) * 1000;
		echo "[{$time_now_stamp}, {$user['rating']}, 0]";
	} else {
		$rating_delta = $user['rating'] - $last_rating;
		echo "[$last_contest_time, {$user['rating']}, $last_contest_id, '$last_contest_name', $last_rank, $rating_delta]";
	}
	if ($user['rating'] < $user_rating_min) {
		$user_rating_min = $user['rating'];
	}
	if ($user['rating'] > $user_rating_max) {
		$user_rating_max = $user['rating'];
	}
		
	$user_rating_min -= 200;
	$user_rating_max += 200;
?>
]];
var rating_plot = $.plot($("#rating-plot"), [{
	color: "#3850eb",
	label: "<?= $user['username'] ?>",
	data: rating_data[0]
}], {
	series: {
		lines: {
			show: true
		},
		points: {
			show: true
		}
	},
	xaxis: {
		mode: "time",
		timeBase: "milliseconds"
	},
	yaxis: {
		autoScale: "none",
		min: <?= $user_rating_min ?>,
		max: <?= $user_rating_max ?>
	},
	grid: {
		clickable: true,
		hoverable: true
	},
	hooks: {
		drawBackground: [
			function(plot, ctx) {
				var plotOffset = plot.getPlotOffset();
				for (var y = 0; y < plot.height(); y++) {
					var rating = <?= $user_rating_max ?> - <?= $user_rating_max - $user_rating_min ?> * y / plot.height();
					ctx.fillStyle = getColOfRating(rating);
					ctx.fillRect(plotOffset.left, plotOffset.top + y, plot.width(), Math.min(5, plot.height() - y));
				}
			}
		]
	}
});

function showTooltip(x, y, contents) {
    $('<div id="rating-tooltip">' + contents + '</div>').css({
	position: 'absolute',
	display: 'none',
	top: y - 20,
	left: x + 10,
	border: '1px solid #fdd',
	padding: '2px',
	'font-size' : '11px',
	'background-color': '#fee',
	opacity: 0.80
    }).appendTo("body").fadeIn(200);
}

var prev = -1;
function onHoverRating(event, pos, item) {
	if (prev != item.dataIndex) {
		$("#rating-tooltip").remove();
		var params = rating_data[item.seriesIndex][item.dataIndex];

		var total = params[1];
		var contestId = params[2];
		if (contestId != 0) {
			var change = params[5] > 0 ? "+" + params[5] : params[5];
			var contestName = params[3];
			var rank = params[4];
			var html = "= " + total + " (" + change + "), <br/>"
			+ "Rank: " + rank + "<br/>"
	    + '<a href="' + '/contest/' + contestId + '">' + contestName + '</a>';
		} else {
			var html = "= " + total + "<br/>"
			+ "Unrated";
		}
		showTooltip(item.pageX, item.pageY, html);
		prev = item.dataIndex;
	}
}
$("#rating-plot").bind("plothover", function (event, pos, item) {
    if (item) {
    	onHoverRating(event, pos, item);
    }
});
$("#rating-plot").bind("plotclick", function (event, pos, item) {
    if (item && prev == -1) {
    	onHoverRating(event, pos, item);
    } else {
		$("#rating-tooltip").fadeOut(200);
		prev = -1;
	}
});
</script>
<?php if ($setting['background']!=''): ?>
<script type="text/javascript">
	$(document.body).append('<div class="cover bg-opacity-3"></div>');
	$(".container").css("border-radius","50px");
	$(".container").addClass("bg-opacity-3");
	$(".navbar").addClass("bg-opacity-3");
	$(".panel").addClass("bg-opacity-3");
	$(".list-group-item").addClass("bg-opacity-3");
	$("pre").addClass("bg-opacity-3");
	$(document.body).css({
		'background':"url(<?= HTML::escape($setting['background']) ?>)",
		'background-position':'center',
		'background-size':'cover',
		'background-attachment':'fixed'
	});
</script>
<?php endif ?>
<?php if (isset($setting['nest'])): ?>
	<script type="text/javascript" color="0,0,255" opacity='1' zIndex="-2" count="99" src="/min/js?rq=%7B%22canvas-nest%22%3A%22%22%7D"></script>
<?php endif ?>

<?php else: ?>

<?php echoUOJPageHeader('不存在该用户' . ' - 用户信息') ?>
<div class="panel panel-danger">
	<div class="panel-heading">用户信息</div>
	<div class="panel-body">
		<h4>不存在该用户</h4>
	</div>
</div>
	
<?php endif ?>

<?php echoUOJPageFooter() ?>