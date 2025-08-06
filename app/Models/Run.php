<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Run extends Model
{
    protected $fillable = ['date', 'miles', 'minutes', 'seconds'];

    // Calculate average pace (min/mile) across all runs
    public function averagePace() {
        $totalMiles = 0;
        $totalMinutes = 0;
        foreach ($this->runs as $run) {
            $totalMiles += $run['miles'];
            $totalMinutes += $run['minutes'] + $run['seconds'] / 60;
        }
        return $totalMiles > 0 ? round($totalMinutes / $totalMiles, 2) : 0;
    }

    // Calculate current streak (consecutive days up to today)
    public function streak() {
        if (empty($this->runs)) return 0;
        $dates = array_map(function($run) { return $run['date']; }, $this->runs);
        $dates = array_unique($dates);
        $dates = array_map(function($d) { return new \DateTime($d); }, $dates);
        usort($dates, function($a, $b) { return $b <=> $a; }); // Descending

        $streak = 0;
        $today = new \DateTime();
        foreach ($dates as $date) {
            if ($date->diff($today)->days === $streak && $date <= $today) {
                $streak++;
            } else {
                break;
            }
        }
        return $streak;
    }

    // Calculate pace (min/mile) for a single run
    public function paceForRun($run) {
        $miles = $run['miles'];
        $minutes = $run['minutes'] + $run['seconds'] / 60;
        return $miles > 0 ? round($minutes / $miles, 2) : 0;
    }

    // Get the total number of runs
    public function totalRuns() {
        return count($this->runs);
    }

    // Get the total time running in hours
    public function totalHours() {
        $totalMinutes = 0;
        foreach ($this->runs as $run) {
            $totalMinutes += $run['minutes'] + $run['seconds'] / 60;
        }
        return round($totalMinutes / 60, 2);
    }

    // Calculate streak ending at a specific date (YYYY-MM-DD)
    public function streakAtDate($targetDate) {
        if (empty($this->runs)) return 0;
        // Collect unique dates up to and including $targetDate
        $dates = array_map(function($run) { return $run['date']; }, $this->runs);
        $dates = array_unique($dates);
        $dates = array_filter($dates, function($d) use ($targetDate) {
            return $d <= $targetDate;
        });
        if (empty($dates)) return 0;
        $dates = array_map(function($d) { return new \DateTime($d); }, $dates);
        usort($dates, function($a, $b) { return $b <=> $a; }); // Descending

        $streak = 0;
        $current = new \DateTime($targetDate);
        foreach ($dates as $date) {
            if ($date->diff($current)->days === $streak && $date <= $current) {
                $streak++;
            } else {
                break;
            }
        }
        return $streak;
    }

    // Get the total distance in miles
    public function totalDistance() {
        $total = 0;
        foreach ($this->runs as $run) {
            $total += $run['miles'];
        }
        return $total;
    }
}