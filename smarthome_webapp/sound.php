<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Speaker</title>
    <?php 
    $pageTitle = "Speaker Control";
    include 'common_styles.php';
    ?>
    <style>
        /* Keep only the speaker-specific styles */
        .speaker-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Rest of the speaker styles... */
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>

    <div class="speaker-container">
        <div class="speaker-icon">🔊</div>
        
        <div class="volume-control">
            <span>🔈</span>
            <input type="range" 
                   class="volume-slider" 
                   min="0" 
                   max="100" 
                   value="50" 
                   id="volumeSlider">
            <span class="volume-value" id="volumeValue">50%</span>
        </div>

        <div class="playback-controls">
            <button class="control-btn" id="playPauseBtn" onclick="togglePlayPause()">
                ▶
            </button>
        </div>
    </div>

    <script>
        const volumeSlider = document.getElementById('volumeSlider');
        const volumeValue = document.getElementById('volumeValue');
        const playPauseBtn = document.getElementById('playPauseBtn');
        let isPlaying = false;

        // Volume slider control
        volumeSlider.addEventListener('input', function() {
            const value = this.value;
            volumeValue.textContent = value + '%';
            // Here you would typically add code to actually change the speaker volume
        });

        // Play/Pause toggle
        function togglePlayPause() {
            isPlaying = !isPlaying;
            if (isPlaying) {
                playPauseBtn.innerHTML = '⏸';
                playPauseBtn.classList.add('playing');
                // Here you would typically add code to start playing music
            } else {
                playPauseBtn.innerHTML = '▶';
                playPauseBtn.classList.remove('playing');
                // Here you would typically add code to pause music
            }
        }
    </script>
</body>
</html>
