// script.js

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    if (!calendarEl) {
        console.error('Calendar element not found');
        return;
    }

    // Initialize calendar
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        editable: true,
        events: 'events.json',
        dateClick: function(info) {
            openModal(info.dateStr);
        },
        eventClick: function(info) {
            if (confirm('Do you want to delete this event?')) {
                deleteEvent(info.event);
            }
        }
    });

    calendar.render();

    // Modal functions
    var modal = document.getElementById('event-modal');
    var form = document.getElementById('event-form');

    function openModal(dateStr) {
        document.getElementById('event-date').value = dateStr;
        modal.style.display = 'block';
    }

    window.closeModal = function() {
        modal.style.display = 'none';
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var title = document.getElementById('event-title').value;
        var date = document.getElementById('event-date').value;

        fetch('add_event.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'title=' + encodeURIComponent(title) + '&date=' + encodeURIComponent(date)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                calendar.addEvent({
                    title: title,
                    start: date
                });
                closeModal();
                form.reset();
            } else {
                alert('Failed to add event');
            }
        });
    });

    function deleteEvent(event) {
        fetch('delete_event.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'title=' + encodeURIComponent(event.title) + '&date=' + encodeURIComponent(event.startStr)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                event.remove();
            } else {
                alert('Failed to delete event');
            }
        });
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
});
