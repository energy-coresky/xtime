<?php

class Calendar {
    static $me = false;
    private $month_names;
    private $week_names;
    private $month_ary = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    private $week_ary = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    public $now;
    public $year;
    public $month;
    public $week_i = 0;
    public $data = [];
    public $timeline = true; # for day and week

    function __construct($type, $ts, $f, $func) {
        $this->now = getdate();
        $this->dt = getdate($ts);
        $this->month = $this->dt['mon']; # 1..12
        $this->year = $this->dt['year'];
        $this->type = $type;
        $this->f = $f;
        $this->func = $func;
    }

    function names($left = 0) {
        global $user;

        $is_my = 'MySQLi' == SQL::$dd->name;
        for ($q = '', $i = 1; $i <= 12; $i++)
            $q .= "date_format('2019-$i-1', '%M')" . ($i == 12 ? '' : ',');
        $this->month_names = $is_my ? sqlf('-select ' . $q) : $this->month_ary;
        $m = $user->u_sweek ? (2 == $user->u_sweek ? 6 : 9) : 4;
        for ($q1 = $q2 = $q3 = '', $i = 1; $i <= 7; $i++) {
            $comma = $i == 7 ? '' : ',';
            $q1 .= "date_format('2019-$m-$i', '%a')$comma";
            $q2 .= 'date_format(%1$s + interval ' . ($i - 1) . " day, '%%e %%M'),";
            $q3 .= 'day(%1$s + interval ' . ($i - 1) . " day)$comma";
        }
        $current = $this->year == $this->now['year'] && $this->month == $this->now['mon'];
        $days = false;
        if ($left) { # weeks only
            $days = sqlf('-select ' . $q2 . $q3, $left);
            $this->data = array_combine(array_splice($days, 7, 7), array_pad([], 7, []));
        }
        $ary = $is_my ? sqlf('-select ' . $q1) : $this->week_ary;
        $this->week_names = array_map(function ($k, $v) use ($m, $days, $current) {
            $cls = 4 == $m && (5 == $k || 6 == $k)
                || 6 == $m && (0 == $k || 1 == $k)
                || 9 == $m && (0 == $k || 6 == $k) ? 'week-end' : 'week-day';
            if ($days) {
                $s = 'style="font-size:70%"';
                if ($current && $days[$k] == $this->now['mday'])
                    $s .= ' class="week-now"';
                $d = 'calendar&dt=d' . $this->month . '_' . (int)($days[$k]);
                $v .= '<br>' . a($days[$k], 'javascript:;', "$s onclick=\"run(['activity','$d'])\"");
            }
            return sprintf('<th class="%s">%s</th>', $cls, $v);
        }, array_keys($ary), $ary);
    }

    static function type() {
        $s = 'class="cal-view" ';
        return 'Show: '
            . a('Year', 'javascript:;', $s . 'onclick="run([\'activity\',\'calendar&dt=y\'])"') . ' &#9658; '
            . a('Month', 'javascript:;', $s . 'onclick="run([\'activity\',\'calendar&dt=m\'])"') . ' &#9658; '
            . a('Week', 'javascript:;', $s . 'onclick="run([\'activity\',\'calendar&dt=w\'])"') . ' &#9658; '
            . a('Day', 'javascript:;', $s . 'onclick="run([\'activity\',\'calendar&dt=d\'])"');
    }

    static function decode(&$type, &$ts, $in) {
        $ts or $ts = time();
        if (!$in)
            return;
        $dt = getdate($ts);
        if (preg_match("/^d((\d+)_(\d+)(_\d+)?)?$/", $in, $m)) {
            $type = 'day';
            if (isset($m[1]))
                $ts = isset($m[4])
                    ? mktime(0, 0, 0, $m[3], substr($m[4], 1), $m[2])
                    : mktime(0, 0, 0, $m[2], $m[3], $dt['year']);
        } elseif (preg_match("/^m(\d+)?(_\d+)?$/", $in, $m)) {
            $type = 'month';
            if (isset($m[1]))
                $ts = isset($m[2])
                    ? mktime(0, 0, 0, $m[1], substr($m[2], 1), $dt['year'])
                    : mktime(0, 0, 0, $m[1], 1, $dt['year']);
        } elseif (preg_match("/^y(\d+)?$/", $in, $m)) {
            $type = 'year';
            if (isset($m[1]))
                $ts = mktime(0, 0, 0, 1, 1, $m[1]);
        } elseif (preg_match("/^w(\d+)?$/", $in, $m)) {
            $type = 'week';
            if (isset($m[1])) {
                $m[1] <= 53 or $m[1] = 1;
                $ts = sqlf('+select unix_timestamp("%d-1-1" + interval %d week)', $dt['year'], $m[1] - 1);
            }
        } elseif (preg_match("/^p|c|n$/", $in, $m)) { # Prev Curr Next
            switch ($in) {
                case 'p': $ts = sqlf("+select unix_timestamp(from_unixtime(%d) - interval 1 $type)", $ts); break;
                case 'c': $ts = time(); break;
                case 'n': $ts = sqlf("+select unix_timestamp(from_unixtime(%d) + interval 1 $type)", $ts); break;
            }
        }
    }

    static function draw($type, $ts = 0, $f = 0, $func = 0) {
        is_num($ts, false, false) or $ts = 0;
        $ts or $ts = time();
        if (self::$me)
            return self::$me->_month(); # draw next months for year view
        self::$me = new Calendar($type, $ts, $f, $func);

        return call_user_func([self::$me, '_' . $type], $ts);// + ['js' => js('ab.calendar()')];
    }

    function _year($ts) {
        $this->timeline = false;
        $this->month = 1; # Year start from January
        $n = $this->dt['year'];
        $ary = call_user_func($this->func, $this, "$n-01-01", "$n-12-31");
        $opt = range($n - 9, $n + 9 > 2037 ? 2037 : $n + 9);
        return $ary + ['select' => tag(option($n, array_combine($opt, $opt)), 'id="year"', 'select')];
    }

    function _month($ts = 0) {
        $this->timeline = false;
        global $user;
        $this->month_names or $this->names();
        $first = getdate($mts = mktime(0, 0, 0, $this->month, 1, $this->year));
        $last = getdate(mktime(0, 0, 0, $this->month + 1, 0, $this->year));
        $current = $this->year == $this->now['year'] && $this->month == $this->now['mon'];
        $wd = $first['wday'];
        if (!$user->u_sweek)
            $wd = $wd ? $wd - 1 : 6;
        if (2 == $user->u_sweek)
            $wd = $wd < 6 ? $wd + 1 : 0;
        $row = $ary = [];
        if ($ts) { # single month
            $this->week_i = sqlf("+select strftime('%%W', '$this->year-$this->month-1') - 1");
            $mon = $this->month < 10 ? "0$this->month" : $this->month;
       #     $left = "$this->year-$mon-01";
          #  $right = sqlf('+select %s + interval 1 month - interval 1 day', $left);
            //$ary = call_user_func($this->func, $this, $left, $right);
        }
        $mon = isset($this->data[$this->month]) ? $this->data[$this->month] : [];
        $em = 0;
        $mark = $ts ? 'the-mark-big' : 'the-mark';
        for ($d = 1; true; ) {
            $s = '';
            for ($i = 0; $i < 7; $i++, $d++) {
                if ($i >= $wd && !$em)
                    $d = $em = 1;
                if ($d > $last['mday'] && 1 == $em)
                    $em = 2;
                $td = '<td>&nbsp;';
                if (1 == $em) {
                    $class = isset($mon[$d]) ? $mark : 'the-day';
                    if ($ts && $d == $this->dt['mday'])
                        $class .= '" style="border-top:2px solid #748aac; border-left:2px solid #748aac;';
                    $td = $current && $d == $this->now['mday']
                        ? '<td class="the-now ' . $class . '" title="today">' . $d
                        : '<td class="' . $class . '">' . $d;
                }
                $s .= "$td</td>";
            }
            $row[] = $s . '<td class="the-week">' . ++$this->week_i . '</td>';
            if ($d > $last['mday']) {
                1 == $em or --$this->week_i;
                break;
            }
        }
trace($ary);
        $ary += [
            'year' => $this->year,
            'mon' => $this->month,
            'name' => $this->month_names[$this->month++ - 1] . ($ts ? " $this->year" : ''),
            'head' => implode('', $this->week_names),
            'weeks' => $row,
    //        'ts' => $ts,
     //       'viewday' => '---',
        ];
        return $ary;
    }

    function _week($ts) {
        global $sky, $user;
        $dt = getdate($ts);
        $wd = $dt['wday'];
        if (!$user->u_sweek)
            $wd = $wd ? $wd - 1 : 6;
        if (2 == $user->u_sweek)
            $wd = $wd < 6 ? $wd + 1 : 0;
        if ($wd)
            $ts -= $wd * 3600 * 24;
        $this->names($left = substr(date(DATE_DT, $ts), 0, 10));
        $right = sqlf('+select %s + interval 6 day', $left);
        $ary = call_user_func($this->func, $this, $left, $right);
        $week = (int)sqlf("+select date_format('$dt[year]-$dt[mon]-$dt[mday]', '%v')");
        return $ary + [
            'day' => $left,
            'name' => "Week $week, $dt[year]",
            'head' => implode('', $this->week_names),
            'svg' => $sky->s_ab_tbeg . ':' . $sky->s_ab_tend . ':'
                . (!$sky->s_ab_tstep ? 15 : (1 == $sky->s_ab_tstep ? 20 : 30)),
            'height' => ($sky->s_ab_tend - $sky->s_ab_tbeg) * 84 + 31,
        ];
    }

    function _day($ts) {
        global $sky, $user;
        $day = "$this->year-$this->month-" . $this->dt['mday'];
        $now = $day == $this->now['year'] . '-' . $this->now['mon'] . '-' . $this->now['mday'];
        $ary = call_user_func($this->func, $this, $day, $day);
        return $ary + [
            'now' => $now ? ' ' . tag('Today', 'class="now-day"', 'span') : '',
            'day' => $day,
            'name' => sqlf('+select date_format(%s, "%%e %%M %%Y")', $day),
            'svg' => $sky->s_ab_tbeg . ':' . $sky->s_ab_tend . ':'
                . (!$sky->s_ab_tstep ? 15 : (1 == $sky->s_ab_tstep ? 20 : 30)),
            'height' => ($sky->s_ab_tend - $sky->s_ab_tbeg) * 84 + 31,
        ];
    }
}
