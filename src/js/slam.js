let idleTimer; // Variable to hold the timeout ID
let timeoutDuration; // Variable to hold the timeout duration (in milliseconds)

async function getCourseTools() {
	// remove any tools currently displayed and show a loading wheel
	toolList = document.getElementById('toolList');
	toolList.classList.add('loading');
	toolList.innerHTML = "<div><img src='images/loading.gif' alt='Please wait while the list of available tools loads.'></div>";
	
	url = window.location.href.substring(0, document.location.href.lastIndexOf("/")) + '/exceptions.php?action=list';
	fetch(url, {
			method: "GET",
			mode: "no-cors"
		})
		.then(response => response.json()).then(data => {
			toolHTML = '';
			notificationsHTML = '';
			for (var key in data) {
				toolHTML += "<div id='lti_tool_" + data[key]['id'] + "' class='lti-tool";
				if (data[key]['enabled']) toolHTML += " lti-tool-enabled";
				toolHTML += "'><div class='switch' id='switch_" + data[key]['id'] + "' onclick='tool_select_" +
					data[key]['id'] + ".click();'><input type='checkbox' id='tool_select_" + data[key]['id'] +
					"' onchange='updateToolInstall(" + data[key]['id'] + ");'";
				if (data[key]['enabled']) toolHTML += " checked";
				toolHTML += "><span class='slider round'></span></div><div><label for='tool_select_" +
					data[key]['id'] + "' class='toggle-label'>" + data[key]['name'] + "</label></div>";
				if ('support_info' in data[key] && data[key]['support_info'] !== null && data[key]['support_info'].length > 0) {
					toolHTML += "<div class='tool-support'>";
					toolHTML += data[key]['support_info'].replaceAll('\[TOOL_NAME\]', data[key]['name']);
					toolHTML += "</div>";
				}
				if ('user_notice' in data[key] && data[key]['user_notice'] !== null && data[key]['user_notice'].length > 0) {
					notificationsHTML += "<div class='tool-message' id='tool_message_" + data[key]['id'] + "'>\n";
					notificationsHTML += "  <div id='tool_message_text_" + data[key]['id'] + "'>" + data[key]['user_notice'] + "</div>\n";
					notificationsHTML += "  <div style='clear:both; text-align:center;'>\n";
					notificationsHTML += "    <input type='button' class='tool-message-button' value='Cancel' onclick='toolNoticeResponse(" + data[key]['id'] + ", true);'>\n";
					notificationsHTML += "    <input type='button' class='tool-message-button' value='OK' onclick='toolNoticeResponse(" + data[key]['id'] + ", false);'>\n";
					notificationsHTML += "  </div>\n</div>";
				}
				toolHTML += "</div>";
			}
			toolList.classList.remove('loading');
			toolList.innerHTML = toolHTML;
			document.getElementById('messageBoxes').innerHTML = notificationsHTML;
		}).catch(error => {
			console.log(error);
		});
}

async function updateToolInstall(tool_id, confirmed = false) {
	tool_toggle = document.getElementById("tool_select_" + tool_id);
	tool_container = document.getElementById('lti_tool_' + tool_id);
	url = window.location.href.substring(0, document.location.href.lastIndexOf("/")) + '/exceptions.php?tool_id=';
	url += tool_id + '&action=';
	url += tool_toggle.checked ? 'add' : 'remove';
	tool_toggle.disabled = true;
	if (document.getElementById("tool_message_" + tool_id) != null) {
		document.getElementById("tool_message_" + tool_id).style.display = "none";
	}
	fetch(url, {
			method: "GET",
			mode: "no-cors"
		})
		.then(response => response.json()).then(data => {
			if (Object.hasOwn(data, 'success') && data['success']) {
				for (var key in data['changed']) {
					var toggle_id = 'tool_select_' + data['changed'][key];
					var tool_container_id = 'lti_tool_' + data['changed'][key];
					var message_box_id = 'tool_message_' + data['changed'][key];
					if (document.getElementById(toggle_id) != null) {
						if (data['action'] == 'add') {
							document.getElementById(toggle_id).checked = true;
							document.getElementById(tool_container_id).classList.add("lti-tool-enabled");
							// check if there is a notification associated with the tool and show it when trying to add it
							if (document.getElementById(message_box_id) != null) {
								message_box = document.getElementById("tool_message_text_" + data['changed'][key]);
								tool_detail = data['details'][data['changed'][key]];
								if (Object.hasOwn(tool_detail, 'deployment_id') && tool_detail['deployment_id'] != '')
									message_box.innerHTML = message_box.innerHTML.replaceAll('\[DEPLOYMENT_ID\]', tool_detail['deployment_id']);
								else
									message_box.innerHTML = message_box.innerHTML.replaceAll('\[DEPLOYMENT_ID\]', '(No course-level Deployment ID)');
								message_box.innerHTML = message_box.innerHTML.replaceAll('\[TOOL_NAME\]', tool_detail['name']);
								document.getElementById(message_box_id).style.top = (tool_toggle.getBoundingClientRect().top - document.body.getBoundingClientRect().top) + "px";
								document.getElementById(message_box_id).style.display = "block";
							}
						} else {
							document.getElementById(toggle_id).checked = false;
							document.getElementById(tool_container_id).classList.remove("lti-tool-enabled");
						}
					}
				}
				if (Object.hasOwn(data, 'message')) {
					// display the message somehow
				}
			} else {
				console.log(data['errors']);
				if (data['action'] == 'add') {
					tool_toggle.checked = false;
					tool_container.classList.remove("lti-tool-enabled");
				} else {
					tool_toggle.checked = true;
					tool_container.classList.add("lti-tool-enabled");
				}				
			}
		}).catch(error => {
			console.log(error);
		});
	tool_toggle.disabled = false;
}

function toolNoticeResponse(tool_id, cancelAdd) {
	if (document.getElementById("tool_message_" + tool_id) != null)
		document.getElementById("tool_message_" + tool_id).style.display = "none";
	if (cancelAdd)
		document.getElementById("tool_select_"+tool_id).click();
}

function setToolContainerSize() {
	toolContainerHeight = parseInt(window.innerHeight) -
		parseInt(document.getElementById('slamTitle').clientHeight) -
		parseInt(document.getElementById('slamDescription').clientHeight) -
		parseInt(document.getElementById('courseTitle').clientHeight) - 100;
	document.getElementById('toolList').style.height = toolContainerHeight + "px";
}

function initializeTimer(duration = 120000) { // set the default value of the timer to 2 minutes
	timeoutDuration = duration;
	// Event listeners to detect user activity
	document.addEventListener('mousemove', resetTimer);
	document.addEventListener('keydown', resetTimer);
	document.addEventListener('click', resetTimer);

	// Start the timer initially
	resetTimer();
}

function onIdle() {
	// Set the body of the page to ask user to relaunch SLAM
	relaunchSLAM = `
<div id='slamTitle' class='slam-title'>
<div style='width: 100%;'>
	<h1><img src='https://www.uky.edu/canvas/branding/slam.png' style='height:1.2em;' alt='SLAM logo'>Self-Service LTI App Management</h1>
</div>
</div>
<h2>Page timeout</h2>
<p>Your session has timed out. Please re-launch SLAM from the course menu.</p>`;
	document.body.innerHTML = relaunchSLAM;
}

function resetTimer() {
	clearTimeout(idleTimer); // Clear the previous timer
	idleTimer = setTimeout(onIdle, timeoutDuration); // Set a new one
}