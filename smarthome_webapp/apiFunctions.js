/* Provides helper function to link front-end and backend
* @param {string} endpoint - API endpoint
* @param {string} method - HTTP method being used
* @param {object} payload - Data sent in request
* @param {function} callback - Callback function with result, error code
*/

function apiRequest(endpoint, method, payload, callback) {
	const options = {
		method: method.toUpperCase(),
		headers: {
			'Content-Type': 'application/json',
			'X-Requested-With': 'XMLHttpRequest'
		}
	};

	if (options.method !== 'GET' && payload) {
		options.body = JSON.stringify(payload);
	}

	fetch(endpoint, options)
	.then(response => response.json())
	.then(result => {
		if (callback) {
			callback(result, null);
		}
	})
	.catch(error => {
		if (callback) {
			callback(null, error);
		}
	});
}


function showNotification(message, isSuccess = true, duration = 2500) {
	let notification = document.getElementById('notification');
	if (!notification) {
		notification = document.createElement('div');
		notification.id = 'notification';
		notification.style.position = 'fixed';
		notification.style.top = '20px';
		notification.style.right = '20px';
		notification.style.padding = '10px 20px';
		notification.style.color = 'white';
		notification.style.borderRadius = '5px';
		notification.style.boxShadow = '0 0 10px rgba(0,0,0,0.5)';
		notification.style.zIndex = '10000';
		notification.style.display = 'none';
		document.body.appendChild(notification);
	}
	notification.textContent = message;
	notification.style.backgroundColor = isSuccess ? 'green' : 'red';
	notification.style.display = 'block';
	setTimeout(() => {
		notification.style.display = 'none';
	}, duration);
}
