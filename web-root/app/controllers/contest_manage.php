<?php
	requirePHPLib('form');
	
	if (!validateUInt($_GET['id']) || !($contest = queryContest($_GET['id']))) {
		become404Page();
	}
	genMoreContestInfo($contest);
	
	if (!hasContestPermission($myUser, $contest)) {
		become403Page();
	}
	
	$time_form = new UOJForm('time');
	$time_form->addInput(
		'name', 'text', '比赛标题', $contest['name'],
		function($str) {
			return '';
		},
		null
	);
	$time_form->addInput(
		'start_time', 'text', '开始时间', $contest['start_time_str'],
		function($str, &$vdata) {
			try {
				$vdata['start_time'] = new DateTime($str);
			} catch (Exception $e) {
				return '无效时间格式';
			}
			return '';
		},
		null
	);
	$time_form->addInput(
		'last_min', 'text', '时长（单位：分钟）', $contest['last_min'],
		function($str) {
			return !validateUInt($str) ? '必须为一个整数' : '';
		},
		null
	);
	$time_form->handle = function(&$vdata) {
		global $contest;
		$start_time_str = $vdata['start_time']->format('Y-m-d H:i:s');
		
		$purifier = HTML::pruifier();
		
		$esc_name = $_POST['name'];
		$esc_name = $purifier->purify($esc_name);
		$esc_name = DB::escape($esc_name);
		
		DB::update("update contests set start_time = '$start_time_str', last_min = {$_POST['last_min']}, name = '$esc_name' where id = {$contest['id']}");
		DB::manage_log('contest','set contest '.$contest['id'].': start_time='.$start_time_str.', last_min='.$_POST['last_min'].', name='.$esc_name);
	};
	$time_form->runAtServer();
	
	if (isSuperUser($myUser)) {
		$managers_form = newAddDelCmdForm('managers',
			function($username) {
				if (!validateUsername($username) || !queryUser($username)) {
					return "不存在名为{$username}的用户";
				}
				return '';
			},
			function($type, $username) {
				global $contest;
				if ($type == '+') {
					DB::query("insert into contests_permissions (contest_id, username) values (${contest['id']}, '$username')");
				} else if ($type == '-') {
					DB::query("delete from contests_permissions where contest_id = ${contest['id']} and username = '$username'");
				}
				DB::manage_log('contest','update contest '.$contest['id'].' managers '.$type.$username);
			}
		);
		$managers_form->runAtServer();
	}
	
	$problems_form = newAddDelCmdForm('problems',
		function($cmd) {
			if (!preg_match('/^(\d+)\s*(\[\S+\])?$/', $cmd, $matches)) {
				return "无效题号";
			}
			$problem_id = $matches[1];
			if (!validateUInt($problem_id) || !($problem = queryProblemBrief($problem_id))) {
				return "不存在题号为{$problem_id}的题";
			}
			if (!hasProblemPermission(Auth::user(), $problem)) {
				return "无权添加题号为{$problem_id}的题";
			}
			return '';
		},
		function($type, $cmd) {
			global $contest;
			
			if (!preg_match('/^(\d+)\s*(\[\S+\])?$/', $cmd, $matches)) {
				return "无效题号";
			}
			
			$problem_id = $matches[1];
			
			if ($type == '+') {
				DB::insert("insert into contests_problems (contest_id, problem_id) values ({$contest['id']}, '$problem_id')");
			} else if ($type == '-') {
				DB::delete("delete from contests_problems where contest_id = {$contest['id']} and problem_id = '$problem_id'");
			}
			
			if (isset($matches[2])) {
				switch ($matches[2]) {
					case '[sample]':
						unset($contest['extra_config']["problem_$problem_id"]);
						break;
				/*	case '[full]':
						$contest['extra_config']["problem_$problem_id"] = 'full';
						break;*/
					case '[no-details]':
						$contest['extra_config']["problem_$problem_id"] = 'no-details';
						break;
				}
				$esc_extra_config = json_encode($contest['extra_config']);
				$esc_extra_config = DB::escape($esc_extra_config);
				DB::update("update contests set extra_config = '$esc_extra_config' where id = {$contest['id']}");
			}
			DB::manage_log('contest','update contest '.$contest['id'].' problems '.$type.$matches[1].$matches[2]);
		}
	);
	$problems_form->runAtServer();
	
	if (isSuperUser($myUser)) {
		$set_open=new UOJForm('open');
		$set_open->addCheckBox('is_open', '', $contest['is_open']==1);
		$set_open->handle=function(){
			global $contest;
			if($_POST['is_open']){
				DB::query("update contests set is_open=1 where id={$contest['id']}");
			}else{
				DB::query("update contests set is_open=0 where id={$contest['id']}");
			}
		};
		$set_open->runAtServer();
	
		$rating_k_form = new UOJForm('rating_k');
		$rating_k_form->addInput('rating_k', 'text', 'rating 变化上限', isset($contest['extra_config']['rating_k']) ? $contest['extra_config']['rating_k'] : 400,
			function ($x) {
				if (!validateUInt($x) || $x < 1 || $x > 1000) {
					return '不合法的上限';
				}
				return '';
			},
			null
		);
		$rating_k_form->handle = function() {
			global $contest;
			$contest['extra_config']['rating_k'] = $_POST['rating_k'];
			$esc_extra_config = json_encode($contest['extra_config']);
			$esc_extra_config = DB::escape($esc_extra_config);
			DB::update("update contests set extra_config = '$esc_extra_config' where id = {$contest['id']}");
			DB::manage_log('contest','set contest '.$contest['id'].' max_rating='.$_POST['rating_k']);
		};
		$rating_k_form->runAtServer();
		
		$rated_form = new UOJForm('rated');
		$rated_form->handle = function() {
			global $contest;
			if (isset($contest['extra_config']['unrated'])) {
				unset($contest['extra_config']['unrated']);
			} else {
				$contest['extra_config']['unrated'] = '';
			}
			$esc_extra_config = json_encode($contest['extra_config']);
			$esc_extra_config = DB::escape($esc_extra_config);
			DB::update("update contests set extra_config = '$esc_extra_config' where id = {$contest['id']}");
			DB::manage_log('contest','set contest '.$contest['id'].' '.(isset($contest['extra_config']['unrated']) ? 'unrated' : 'rated'));
		};
		$rated_form->submit_button_config['class_str'] = 'btn btn-warning btn-block';
		$rated_form->submit_button_config['text'] = isset($contest['extra_config']['unrated']) ? '设置比赛为rated' : '设置比赛为unrated';
		$rated_form->submit_button_config['smart_confirm'] = '';
	
		$rated_form->runAtServer();
	}
		
	$version_form = new UOJForm('version');
	$version_form->addInput('standings_version', 'text', '排名版本', $contest['extra_config']['standings_version'],
		function ($x) {
			if (!validateUInt($x) || $x < 1 || $x > 2) {
				return '不是合法的版本号';
			}
			return '';
		},
		null
	);
	$version_form->handle = function() {
		global $contest;
		$contest['extra_config']['standings_version'] = $_POST['standings_version'];
		$esc_extra_config = json_encode($contest['extra_config']);
		$esc_extra_config = DB::escape($esc_extra_config);
		DB::update("update contests set extra_config = '$esc_extra_config' where id = {$contest['id']}");
	};
	$version_form->runAtServer();
	
	$compile_option = json_decode($contest['extra_config']['compile_option'],true);
	$compile_option_form = new UOJForm('compile_option');
	$compile_option_form->addCheckBox('set_compile_option', '', isset($compile_option));
	$compile_option_form->addSelect('optimized',array(
		'Default' => 'Default',
		'-O1' => '-O1',
		'-O2' => '-O2',
		'-O3' => '-O3',
		'-Ofast' => '-Ofast(-O4)'
	),'优化等级',isset($compile_option) ? $compile_option['C++'][0] : '');
	$compile_option_form->addSelect('cppstandard',array(
		'Default' => 'Default',
		'-std=c++98' => '-std=c++98',
		'-std=c++03' => '-std=c++03',
		'-std=c++11' => '-std=c++11',
		'-std=c++14' => '-std=c++14',
		'-std=c++17' => '-std=c++17'
	),'C++标准',isset($compile_option) ? $compile_option['C++'][1] : '');
	$compile_option_form->addSelect('cstandard',array(
		'Default' => 'Default',
		'-std=c90' => '-std=c90',
		'-std=c99' => '-std=c99',
		'-std=c11' => '-std=c11'
	),'C标准',isset($compile_option) ? $compile_option['C'][1] : '');
	$compile_option_form->handle = function() {
		global $contest;
		if($_POST['set_compile_option']){
			$compile_option = array(
				'C++' => array($_POST['optimized'], $_POST['cppstandard']),
				'C' => array($_POST['optimized'], $_POST['cstandard']),
				'Pascal' => array($_POST['optimized'] == '-Ofast' ? '-O4' : $_POST['optimized'])
			);
			$contest['extra_config']['compile_option'] = json_encode($compile_option);
		}else
			unset($contest['extra_config']['compile_option']);
		$esc_extra_config = json_encode($contest['extra_config']);
		$esc_extra_config = DB::escape($esc_extra_config);
		DB::update("update contests set extra_config = '$esc_extra_config' where id = {$contest['id']}");
	};
	$compile_option_form->runAtServer();
?>
<?php echoUOJPageHeader(HTML::stripTags($contest['name']) . ' - 比赛管理') ?>
<h1 class="page-header" align="center"><?=$contest['name']?> 管理</h1>
<ul class="nav nav-tabs" role="tablist">
	<li class="active"><a href="#tab-time" role="tab" data-toggle="tab">比赛时间</a></li>
	<?php if (isSuperUser($myUser)): ?>
	<li><a href="#tab-managers" role="tab" data-toggle="tab">管理者</a></li>
	<?php endif ?>
	<li><a href="#tab-problems" role="tab" data-toggle="tab">试题</a></li>
	<li><a href="#tab-others" role="tab" data-toggle="tab">其它</a></li>
	<li><a href="/contest/<?=$contest['id']?>" role="tab">返回</a></li>
</ul>
<div class="tab-content top-buffer-sm">
	<div class="tab-pane active" id="tab-time">
		<?php $time_form->printHTML(); ?>
	</div>
	
	<?php if (isSuperUser($myUser)): ?>
	<div class="tab-pane" id="tab-managers">
		<table class="table table-hover">
			<thead>
				<tr>
					<th>#</th>
					<th>用户名</th>
				</tr>
			</thead>
			<tbody>
<?php
	$row_id = 0;
	$result = DB::query("select username from contests_permissions where contest_id = {$contest['id']}");
	while ($row = DB::fetch($result, MYSQLI_ASSOC)) {
		$row_id++;
		echo '<tr>', '<td>', $row_id, '</td>', '<td>', getUserLink($row['username']), '</td>', '</tr>';
	}
?>
			</tbody>
		</table>
		<p class="text-center">命令格式：命令一行一个，+mike表示把mike加入管理者，-mike表示把mike从管理者中移除</p>
		<?php $managers_form->printHTML(); ?>
	</div>
	<?php endif ?>
	
	<div class="tab-pane" id="tab-problems">
		<table class="table table-hover">
			<thead>
				<tr>
					<th>#</th>
					<th>试题名</th>
				</tr>
			</thead>
			<tbody>
<?php
	$result = DB::query("select problem_id from contests_problems where contest_id = ${contest['id']} order by problem_id asc");
	while ($row = DB::fetch($result, MYSQLI_ASSOC)) {
		$problem = queryProblemBrief($row['problem_id']);
		$problem_config_str = isset($contest['extra_config']["problem_{$problem['id']}"]) ? $contest['extra_config']["problem_{$problem['id']}"] : 'sample';
		echo '<tr>', '<td>', $problem['id'], '</td>', '<td>', getProblemLink($problem), ' ', "[$problem_config_str]", '</td>', '</tr>';
	}
?>
			</tbody>
		</table>
		<center>
		<p>命令格式：命令一行一个，-233表示把题号为233的试题从比赛中移除</p>
		<p>+233或+233[sample]表示添加题号为233的试题，比赛时只显示样例的评测结果（OI赛制），<font color="red">赛后需要统一重测</font></p>
<!--		<p>+233[full]表示添加题号为233的试题，比赛时直接显示评测结果，<font color="green">赛后不需要重测</font></p>-->
		<p>+233[no-details]表示添加题号为233的试题，比赛时显示评测结果（ACM、IOI赛制），<font color="green">赛后不需要重测</font></p>
		</center>
		<?php $problems_form->printHTML(); ?>
	</div>
	<div class="tab-pane" id="tab-others">
		<div class="row">
			<?php if (isSuperUser($myUser)): ?>
			<div class="col-sm-4">
				<?php $set_open->printHTML();?>
				<script>
					$('#input-is_open').wrap('<div class="top-buffer-md bot-buffer-md"></div>');
					$('#input-is_open').bootstrapSwitch({
						onText: "公开",
						onColor: 'success',
						offText: "不公开",
						offColor: 'danger',
						labelText: "公开比赛？",
						handleWidth: 100
					});
				</script>
			</div>
			<div class="col-sm-8">
				<h3>Rating控制</h3>
				<div class="row">
					<div class="col-sm-3">
						<?php $rated_form->printHTML(); ?>
					</div>
				</div>
				<div class="top-buffer-sm"></div>
				<?php $rating_k_form->printHTML(); ?>
			</div>
			<?php endif ?>
			<div class="col-sm-6">
				<h3>排名版本控制</h3>
				<p>版本1：排名第二关键字为罚时=最后一次提交时间+20分钟*（提交次数-1），未得分的程序不计罚时</p>
				<p>版本2（默认）：排名第二关键字为程序运行时间之和，未得分的程序的运行时间不计入总运行时间</p>
				<?php $version_form->printHTML(); ?>
			</div>
			<div class="col-sm-6">
				<h3>统一编译选项</h3>
				<?php $compile_option_form->printHTML(); ?>
				<script>
					$('#input-set_compile_option').wrap('<div class="top-buffer-md bot-buffer-md"></div>');
					$('#input-set_compile_option').bootstrapSwitch({
						onText: "启用",
						onColor: 'success',
						offText: "禁用",
						offColor: 'danger',
						labelText: "统一编译选项",
						handleWidth: 100
					});
				</script>
			</div>
		</div>
	</div>
</div>
<?php echoUOJPageFooter() ?>