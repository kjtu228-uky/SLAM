async function getCourseTools() {
	// maybe first show a spinny wheel
	
	// remove any tools currently displayed
	toolList = document.getElementById('toolList');
	toolList.innerHTML = '';
	
	
	url = window.location.href.substring(0, document.location.href.lastIndexOf("/")) + '/exceptions.php?action=list';
	fetch(url, {
			method: "GET",
			mode: "no-cors"
		})
		.then(response => response.json()).then(data => {
			console.log(data);
			for (var key in data) {
				toolHTML = "<div id='lti_tool_" + data[key]['id'] + "' class='lti-tool";
				if (data[key]['enabled']) toolHTML += " lti-tool-enabled";
				toolHTML += "' <div class='switch' id='switch_" + data[key]['id'] + "' onclick='tool_select_" +
					data[key]['id'] + ".click();'><input type='checkbox' id='tool_select_" + data[key]['id'] +
					"' onchange='updateToolInstall(" + data[key]['id'] + ");'";
				if (data[key]['enabled']) toolHTML += " checked";
				toolHTML += "><span class='slider round'></span></div><div><label for='tool_select_" +
					data[key]['id'] + "' class='toggle-label'>" + data[key]['name'] + "</label>";
				if ('support_info' in data[key] && data[key]['support_info'] !== null) {
//					preg_replace('/\[TOOL_NAME\]/', $lti_tool['name'], $lti_tool['support_info'])
					toolHTML += "<div class='tool-support'>" + data[key]['support_info'] + "</div>";
				}
				toolHTML += "</div>";
				toolList.innerHTML += toolHTML;
			}
			
			
/* 			updateToggles(data);
			if (action == 'add' && document.getElementById("tool_message_" + tool_id) != null) {
				message_box = document.getElementById("tool_message_text_" + tool_id);
				message_box.innerHTML = message_box.innerHTML.replaceAll('\[DEPLOYMENT_ID\]', data[tool_id]['deployment_id']);
				message_box.innerHTML = message_box.innerHTML.replaceAll('\[TOOL_NAME\]', data[tool_id]['name']);
				document.getElementById("tool_message_" + tool_id).style.top = (tool_toggle.getBoundingClientRect().top - document.body.getBoundingClientRect().top) + "px";
				document.getElementById("tool_message_" + tool_id).style.display = "block";
			} */
		}).catch(error => {
			console.log(error);
		});
	// hide the spinny
	
}

async function updateToolInstall(tool_id, course_number) {
	tool_toggle = document.getElementById("tool_select_" + tool_id);
	update_path = window.location.href.substring(0, document.location.href.lastIndexOf("/")) + '/update_tool.php?';
	update_path = update_path + 'tool_id=' + tool_id + '&action=';
	action = tool_toggle.checked ? 'add' : 'remove';
	update_path += action;
	tool_toggle.disabled = true;

	if (document.getElementById("tool_message_" + tool_id) != null) {
		document.getElementById("tool_message_" + tool_id).style.display = "none";
	}
	tool_toggle.disabled = false;
	fetch(update_path, {
			method: "GET",
			mode: "no-cors"
		})
		.then(response => response.json()).then(data => {
			updateToggles(data);
			if (action == 'add' && document.getElementById("tool_message_" + tool_id) != null) {
				message_box = document.getElementById("tool_message_text_" + tool_id);
				message_box.innerHTML = message_box.innerHTML.replaceAll('\[DEPLOYMENT_ID\]', data[tool_id]['deployment_id']);
				message_box.innerHTML = message_box.innerHTML.replaceAll('\[TOOL_NAME\]', data[tool_id]['name']);
				document.getElementById("tool_message_" + tool_id).style.top = (tool_toggle.getBoundingClientRect().top - document.body.getBoundingClientRect().top) + "px";
				document.getElementById("tool_message_" + tool_id).style.display = "block";
			}
		}).catch(error => {
			console.log(error);
		});
}

function toolNoticeResponse(tool_id, cancelAdd) {
	if (document.getElementById("tool_message_" + tool_id) != null)
		document.getElementById("tool_message_" + tool_id).style.display = "none";
	if (cancelAdd)
		document.getElementById("tool_select_"+tool_id).click();
}

function updateToggles(toolsStatus) {
 	for (var key in toolsStatus) {
		var toggle = 'tool_select_' + key;
		var tool_container = 'lti_tool_' + key;
		if (document.getElementById(toggle) != null) {
			if (toolsStatus[key]['enabled'] > 0) {
				document.getElementById(toggle).checked = true;
				document.getElementById(tool_container).classList.add("lti-tool-enabled");
			} else {
				document.getElementById(toggle).checked = false;
				document.getElementById(tool_container).classList.remove("lti-tool-enabled");
			}
		}
	}
}

function setToolContainerSize() {
	toolContainerHeight = parseInt(window.innerHeight) -
		parseInt(document.getElementById('slamTitle').clientHeight) -
		parseInt(document.getElementById('slamDescription').clientHeight) -
		parseInt(document.getElementById('courseTitle').clientHeight) - 100;
	document.getElementById('toolList').style.height = toolContainerHeight + "px";
}