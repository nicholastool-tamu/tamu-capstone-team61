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
        /* Dark theme background */
        body {
            background-color: black;
            margin: 0;
            padding: 0;
        }

        /* Main container for speaker controls
         * Uses flexbox for centered column layout
         */
        .speaker-container {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            color: white;
        }

        /* Large speaker emoji positioned at top of screen */
        .speaker-emoji {
            font-size: 100px;
            position: absolute;
            top: 20vh;
            left: 50%;
            transform: translateX(-50%);
        }

        /* Volume control section positioning and layout */
        .volume-control {
            position: absolute;
            top: calc(20vh + 180px);
            width: 100%;
            text-align: center;
        }

        /* Volume display text styling */
        .volume-value {
            font-size: 24px;
            margin-bottom: 16px;
            color: white;
            user-select: none;
            pointer-events: none;
        }

        /* Custom styling for volume slider
         * Includes webkit-specific styles for the slider thumb
         */
        .volume-slider {
            width: 280px;
            height: 4px;
            -webkit-appearance: none;
            background: #555;
            border-radius: 2px;
            margin: 0 auto;
        }

        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            background: #6200EE;
            border-radius: 50%;
            cursor: pointer;
        }

        /* Play/Pause button container */
        .playback-controls {
            position: absolute;
            top: calc(20vh + 300px);
            left: 50%;
            transform: translateX(-50%);
        }

        /* Circular play/pause button styling */
        .control-btn {
            width: 80px;
            height: 80px;
            border-radius: 40px;
            border: none;
            background-color: #6200EE;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>

    <div class="speaker-container">
        <div class="speaker-emoji">🔊</div>
        
        <div class="volume-control">
            <div class="volume-value" id="volumeValue">Volume: 50%</div>
            <input type="range" 
                   class="volume-slider" 
                   min="0" 
                   max="100" 
                   value="50" 
                   id="volumeSlider">
        </div>

        <div class="playback-controls">
            <button class="control-btn" id="playPauseBtn" onclick="togglePlayPause()">
                ▶
            </button>
        </div>
    </div>

    <script>
        // Get references to DOM elements
        const volumeSlider = document.getElementById('volumeSlider');
        const volumeValue = document.getElementById('volumeValue');
        const playPauseBtn = document.getElementById('playPauseBtn');
        let isPlaying = false;

        // Update volume display when slider moves
        volumeSlider.addEventListener('input', function() {
            const value = this.value;
            volumeValue.textContent = `Volume: ${value}%`;
        });


        function togglePlayPause() {
            isPlaying = !isPlaying;
            if (isPlaying) {
                playPauseBtn.innerHTML = '⏸';
                playPauseBtn.classList.add('playing');
            } else {
                playPauseBtn.innerHTML = '▶';
                playPauseBtn.classList.remove('playing');
            }
        }
    </script>
</body>
</html>