// script.js

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    if (!calendarEl) {
        console.error('Calendar element not found');
        return;
    }

    console.log('Initializing calendar...');

    // Initialize calendar
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        editable: true,
        dayCellDidMount: function(info) {
            // Set light on for all days by default
            info.el.classList.add('light-on');
            console.log('Cell mounted:', info.dateStr, 'Light on');
        }
    });

    calendar.render();
    console.log('Calendar rendered');

    // If you have a toggle button, update its initial state
    var toggleButton = document.getElementById('toggleButton');
    if (toggleButton) {
        toggleButton.textContent = 'Turn Lights Off';
    }
});

// If you have a toggle function, update it to start with lights on
function toggleLights() {
    var img = document.getElementById('lightImage');
    if (img.src.includes('light-on.png')) {
        img.src = 'light-off.png';
    } else {
        img.src = 'light-on.png';
    }
}
