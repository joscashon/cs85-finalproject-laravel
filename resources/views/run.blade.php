<head>
    <title>Fitness Run Tracker</title>
    <link href="https://fonts.googleapis.com/css?family=Inter:400,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/runtracker.css') }}">
</head>
<body>
<div class="main-bg">
    <div class="main-container">
        <h1>Fitness Run Tracker</h1>
        <div class="flex-row">
            <div class="add-run">
                <h2>🏃‍♂️ Add a Run</h2>
                <form method="post" action="/add-run">
                    @csrf
                    <label>Date: <input type="date" name="date" required value="{{ date('Y-m-d') }}"></label>
                    <label>Miles: <input type="number" name="miles" step="0.01" min="0" required></label>
                    <label>Minutes: <input type="number" name="minutes" min="0" required></label>
                    <label>Seconds: <input type="number" name="seconds" min="0" max="59" required></label>
                    <button type="submit">Add Run</button>
                </form>

                @if ($errors->any())
                    <div style="color:red;">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            <div class="user-profile">
                <h2>👤 User Profile</h2>
                <form method="post" action="/save-profile">
                    @csrf
                    <label>
                        Fitness Level:
                        <select name="fitness_level" required>
                            <option value="sedentary" {{ (optional($profile)->fitness_level ?? '') == 'sedentary' ? 'selected' : '' }}>Sedentary</option>
                            <option value="lightly active" {{ (optional($profile)->fitness_level ?? '') == 'lightly active' ? 'selected' : '' }}>Lightly Active</option>
                            <option value="active" {{ (optional($profile)->fitness_level ?? '') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="very active" {{ (optional($profile)->fitness_level ?? '') == 'very active' ? 'selected' : '' }}>Very Active</option>
                        </select>
                    </label>
                    <label>
                        Age: <input type="number" name="age" min="1" max="120" required value="{{ optional($profile)->age }}">
                    </label>
                    <label>
                        Sex:
                        <select name="sex" required>
                            <option value="male" {{ (optional($profile)->sex ?? '') == 'male' ? 'selected' : '' }}>Male</option>
                            <option value="female" {{ (optional($profile)->sex ?? '') == 'female' ? 'selected' : '' }}>Female</option>
                            <option value="other" {{ (optional($profile)->sex ?? '') == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </label>
                    <label>
                        Height (in): <input type="number" name="height" min="20" max="100" required value="{{ optional($profile)->height }}">
                    </label>
                    <label>
                        Weight (lbs): <input type="number" name="weight" min="40" max="700" required value="{{ optional($profile)->weight }}">
                    </label>
                    <button type="submit">Save Profile</button>
                </form>

                @if ($errors->profile->any())
                    <div style="color:red;">
                        <ul>
                            @foreach ($errors->profile->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
        <div class="flex-row run-history-row">
            <div class="run-history">
                <h2>📅 Run History</h2>
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Miles</th>
                        <th>Minutes</th>
                        <th>Seconds</th>
                        <th>Pace (min/mile)</th>
                        <th>Streak</th>
                        <th>Action</th>
                    </tr>
                    @foreach ($runs as $run)
                    <tr>
                        <td>{{ $run->date }}</td>
                        <td>{{ $run->miles }}</td>
                        <td>{{ $run->minutes }}</td>
                        <td>{{ $run->seconds }}</td>
                        <td>
                            {{ $run->miles > 0 ? round(($run->minutes + $run->seconds / 60) / $run->miles, 2) : 'N/A' }}
                        </td>
                        <td>
                            {{ $streaks[$run->id] ?? 0 }}
                        </td>
                        <td>
                            <form method="post" action="/delete-run" style="display:inline;">
                                @csrf
                                <button type="submit" name="delete" value="{{ $run->id }}" class="delete-btn">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="stats-ai-row">
            <div class="statistics">
                <h2>📊 Statistics</h2>
                <strong>Most Recent Streak: </strong> {{ $recentStreak }} days<br>
                <strong>Best Streak: </strong> {{ $bestStreak }} days<br>
                <strong>Total Runs: </strong> {{ $totalRuns }}<br>
                <strong>Total Distance: </strong> {{ $totalDistance }} miles<br>
                <strong>Total Time Running: </strong> {{ $totalTime }} hours<br>
                <strong>Average Pace: </strong> {{ $averagePace }} min/mile<br>
            </div>
            <div class="ai-coach-side">
                <h2>✨ AI Running Coach</h2>
                <form method="post" action="/generate-feedback">
                    @csrf
                    <button type="submit" name="generate_feedback" value="1">Generate Feedback</button>
                </form>
                <form method="post" action="/delete-feedback" style="margin-top: 10px;">
                    @csrf
                    <button type="submit" name="delete_feedback" value="1" class="delete-feedback-btn">Delete Feedback</button>
                </form>
            </div>
        </div>
        @if ($ai_feedback_html)
            <div class="ai-feedback">
                <strong>AI Coach Feedback:</strong><br>
                {!! $ai_feedback_html !!}
            </div>
        @endif
    </div>
</div>
</body>