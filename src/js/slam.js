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
				toolHTML += "</div>";
			}
			toolList.classList.remove('loading');
			toolList.innerHTML = toolHTML;
		}).catch(error => {
			console.log(error);
		});
}

async function updateToolInstall(tool_id) {
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
			if ( (Object.hasOwn(data, 'success') && data['success'] && tool_toggle.checked) ||
				(Object.hasOwn(data, 'success') && !data['success'] && !tool_toggle.checked) ) {
					for (var id in data['installed']) {
						var toggle_id = 'tool_select_' + id;
						var tool_container_id = 'lti_tool_' + id;
						if (document.getElementById(toggle_id) != null) {
							document.getElementById(toggle_id).checked = true;
							document.getElementById(tool_container_id).classList.add("lti-tool-enabled");
						}
					}
			} else {
				tool_toggle.checked = false;
				tool_container.classList.remove("lti-tool-enabled");
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