function showScheduleForm() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf = $_SESSION['csrf_token'];
    $errors = $_SESSION['form_errors'] ?? [];
    $old = $_SESSION['old_input'] ?? [];
    unset($_SESSION['form_errors'], $_SESSION['old_input']);
    include __DIR__ . '/templates/header.php';
    if ($errors) {
        echo '<div class="alert alert-danger"><ul>';
        foreach ($errors as $e) {
            echo '<li>' . htmlspecialchars($e) . '</li>';
        }
        echo '</ul></div>';
    }
    $name = htmlspecialchars($old['name'] ?? '');
    $urls = htmlspecialchars($old['urls'] ?? '');
    $frequency = $old['frequency'] ?? '';
    $dayOfWeek = $old['day_of_week'] ?? '';
    $dayOfMonth = $old['day_of_month'] ?? '1';
    $time = htmlspecialchars($old['time'] ?? '');
    echo '<h2>Create New Schedule</h2>';
    echo '<form method="post" action="schedule.php">';
    echo '<input type="hidden" name="csrf_token" value="'.$csrf.'">';
    echo '<div class="form-group"><label for="name">Schedule Name</label>';
    echo '<input type="text" id="name" name="name" value="'.$name.'" required></div>';
    echo '<div class="form-group"><label for="urls">Target URLs (one per line)</label>';
    echo '<textarea id="urls" name="urls" rows="5" required>'.$urls.'</textarea></div>';
    echo '<div class="form-group"><label for="frequency">Frequency</label>';
    echo '<select id="frequency" name="frequency" required>';
    foreach (['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $val=>$label) {
        $sel = $frequency === $val ? ' selected' : '';
        echo "<option value=\"{$val}\"{$sel}>{$label}</option>";
    }
    echo '</select></div>';
    $weeklyDisplay = $frequency === 'weekly' ? '' : 'style="display:none;"';
    $monthlyDisplay = $frequency === 'monthly' ? '' : 'style="display:none;"';
    echo '<div class="form-group" id="weekly-options" '.$weeklyDisplay.'>';
    echo '<label for="day_of_week">Day of Week</label>';
    $dowDisabled = $frequency === 'weekly' ? '' : ' disabled';
    echo '<select id="day_of_week" name="day_of_week"'.$dowDisabled.'>';
    $days = ['1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday','7'=>'Sunday'];
    foreach ($days as $i => $label) {
        $sel = ($dayOfWeek === $i) ? ' selected' : '';
        echo "<option value=\"{$i}\"{$sel}>{$label}</option>";
    }
    echo '</select></div>';
    echo '<div class="form-group" id="monthly-options" '.$monthlyDisplay.'>';
    echo '<label for="day_of_month">Day of Month</label>';
    $domDisabled = $frequency === 'monthly' ? '' : ' disabled';
    echo '<input type="number" id="day_of_month" name="day_of_month" min="1" max="31" value="'.htmlspecialchars($dayOfMonth).'"'.$domDisabled.'>';
    echo '</div>';
    echo '<div class="form-group"><label for="time">Time of Day</label>';
    echo '<input type="time" id="time" name="time" value="'.$time.'" required></div>';
    echo '<button type="submit">Save Schedule</button>';
    echo '</form>';
    echo '<script>
    document.getElementById("frequency").addEventListener("change",function(){
      var weekly=document.getElementById("weekly-options");
      var monthly=document.getElementById("monthly-options");
      var dow=document.getElementById("day_of_week");
      var dom=document.getElementById("day_of_month");
      if(this.value==="weekly"){
        weekly.style.display="block";
        monthly.style.display="none";
        dow.disabled=false;
        dom.disabled=true;
      }
      else if(this.value==="monthly"){
        weekly.style.display="none";
        monthly.style.display="block";
        dow.disabled=true;
        dom.disabled=false;
      }
      else{
        weekly.style.display="none";
        monthly.style.display="none";
        dow.disabled=true;
        dom.disabled=true;
      }
    });
    </script>';
    include __DIR__ . '/templates/footer.php';
}

function submitSchedule() {
    global $pdo;
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }
    $name = trim($_POST['name'] ?? '');
    $urlsRaw = trim($_POST['urls'] ?? '');
    $frequency = $_POST['frequency'] ?? '';
    $dayOfWeek = isset($_POST['day_of_week']) ? intval($_POST['day_of_week']) : null;
    $dayOfMonth = isset($_POST['day_of_month']) ? intval($_POST['day_of_month']) : null;
    $time = $_POST['time'] ?? '';
    if ($frequency !== 'weekly') {
        $dayOfWeek = null;
    }
    if ($frequency !== 'monthly') {
        $dayOfMonth = null;
    }
    $errors = [];
    if ($name === '') {
        $errors[] = 'Schedule name is required.';
    }
    if ($urlsRaw === '') {
        $errors[] = 'At least one URL is required.';
    }
    $urls = array_filter(array_map('trim', explode("\n", $urlsRaw)));
    if (empty($urls)) {
        $errors[] = 'Invalid URLs.';
    } else {
        foreach ($urls as $u) {
            if (!filter_var($u, FILTER_VALIDATE_URL)) {
                $errors[] = "Invalid URL: {$u}";
                break;
            }
        }
    }
    if (!in_array($frequency, ['daily','weekly','monthly'], true)) {
        $errors[] = 'Invalid frequency.';
    }
    if ($frequency === 'weekly' && ($dayOfWeek < 1 || $dayOfWeek > 7)) {
        $errors[] = 'Invalid day of week.';
    }
    if ($frequency === 'monthly' && ($dayOfMonth < 1 || $dayOfMonth > 31)) {
        $errors[] = 'Invalid day of month.';
    }
    if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $time)) {
        $errors[] = 'Invalid time.';
    }
    if ($errors) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['old_input'] = [
            'name' => $name,
            'urls' => $urlsRaw,
            'frequency' => $frequency,
            'day_of_week' => $_POST['day_of_week'] ?? null,
            'day_of_month' => $_POST['day_of_month'] ?? null,
            'time' => $time
        ];
        header('Location: schedule.php', true, 303);
        exit;
    }
    $now = new DateTime('now');
    list($hour, $minute) = explode(':', $time);
    $next = clone $now;
    $next->setTime((int)$hour, (int)$minute, 0);
    if ($frequency === 'daily') {
        if ($next <= $now) {
            $next->modify('+1 day');
        }
    } elseif ($frequency === 'weekly') {
        $currentDow = (int)$now->format('N');
        $diff = ($dayOfWeek + 7 - $currentDow) % 7;
        if ($diff === 0 && $next <= $now) {
            $diff = 7;
        }
        $next->modify("+{$diff} days");
    } elseif ($frequency === 'monthly') {
        $year = (int)$now->format('Y');
        $month = (int)$now->format('m');
        $max = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $day = min($dayOfMonth, $max);
        $next->setDate($year, $month, $day);
        if ($next <= $now) {
            $next->modify('+1 month');
            $year = (int)$next->format('Y');
            $month = (int)$next->format('m');
            $max = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $day = min($dayOfMonth, $max);
            $next->setDate($year, $month, $day);
        }
    }
    $sql = 'INSERT INTO schedules(user_id,name,urls,frequency,day_of_week,day_of_month,time,next_run,created_at)
            VALUES(:uid,:name,:urls,:freq,:dow,:dom,:time,:next,:now)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid' => $_SESSION['user_id'],
        ':name' => $name,
        ':urls' => json_encode($urls),
        ':freq' => $frequency,
        ':dow' => $dayOfWeek,
        ':dom' => $dayOfMonth,
        ':time' => $time,
        ':next' => $next->format('Y-m-d H:i:s'),
        ':now' => date('Y-m-d H:i:s')
    ]);
    header('Location: schedules_list.php?success=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    submitSchedule();
} else {
    showScheduleForm();
}