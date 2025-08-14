$.settings = {
	allowSendingMsgs: true,

	allowConsoleLog: true,
	showConsoleLogLevelAndAbove: 0,
	showConsoleLogTrace: false,

	updateChatWithInterval: true,
	chatUpdateInterval: 5 * 1000,

	playIncommingMsgSound: true,
	incommingMsgSoundUrl: "https://sounddino.com//mp3/5/single-sound-message-icq-ooh.mp3",
	api_full_url: "./api.php?data=",
	defaultChatsLoadingLimiting: 6,
	defaultMsgsLoadingLimiting: 6,
	defaultProfilePicture: "./profile_pics/unknown.jpg",

	popupDefaultOptions: {
		animation: "none",
		title: false,
		content: false,
		theme: "supervan",
		columnClass: 'col-md-12',
		backgroundDismiss: true,
		closeIcon: false,
		draggable: true,
	}
};

$.globals = {
	username: "assaf",
	loggedIn: true,
	lastTimeSentMsg: 0,
	isLoadingMsgs: 0,
	longPressTimer: null,
	thisContact: {
		profile_picture_url: null
	},
};

$.intervals = {};

var consoleLog = function (...args) {

	if (!$.settings.allowConsoleLog) {
		return false;
	}

	var level = 0;
	var type = "log";
	var showTrace = $.settings?.showConsoleLogTrace ?? false;

	if (args.length > 1) {
		var lastArg = args[args.length - 1];
		if (lastArg && typeof lastArg === "object") {

			if ("level" in lastArg) {
				level = lastArg.level;
			}

			if ("type" in lastArg) {
				type = lastArg.type;
			}

			if ("showTrace" in lastArg) {
				showTrace = lastArg.showTrace;
			}

			args.splice(args.length - 1, 1);
		}
	}

	const showLevel = $.settings?.showConsoleLogLevelAndAbove ?? 0;

	if (level === showLevel || level > showLevel) {

		switch (type) {
			default:
			case "log":
				console.log(...args);
				break;

			case "alert":
				popup(jsonEncode({ ...args }));
				break;

			case "info":
				console.info(...args);
				break;

			case "warn":
				console.warn(...args);
				break;

			case "error":
				console.error(...args);
				break;
		}

		if (showTrace) {
			console.trace();
		}

	}
}

var postToServer = async function ($arguments = null) {

	if (typeof $arguments !== "object") {
		var forceRoute = $arguments ?? "";
	}

	var postObj = $arguments?.data ?? arguments?.postObj ?? {};
	postObj.username = $arguments?.data?.username ?? $.globals.username;

	try {
		var token = localStorage.getItem("token");
		if (token) { postObj.token = token; }
	} catch (e) { }
	
	var route = forceRoute ?? $arguments?.route ?? "a";

	if (!route) {
		consoleLog("you're trying to call postToServer function without route. route:", route, { level: 0 });
		return false;
	}

	var url = $.settings.api_full_url + route;
	var method = $arguments?.medthod ?? "POST";
	var successCallback = $arguments?.successCallback ?? $arguments?.onSuccess ?? null;
	var errorCallback = $arguments?.errorCallback ?? null;
	var onAnywayCallback = $arguments?.onAnywayCallback ?? null;
	var asyncValue = $arguments?.async ?? true;

	$.ajax({
		"url": url,
		"method": method,
		"data": postObj,
		"async": asyncValue,
		"error": function (data) {
			if (typeof errorCallback === "function") {
				errorCallback(data);
			}
		},
		"success": function (data) {
			if (typeof successCallback === "function") {
				successCallback(data);
			}
		},
		"complete": function (data) {
			if (typeof onAnywayCallback === "function") {
				onAnywayCallback(data);
			}
		}
	});
}

var base64Encode = function ($str) {
	try {
		return btoa(unescape(encodeURIComponent($str)));
	} catch (e) {
		console.error("base64Encode failed", e);
	}
};

var base64Decode = function ($str) {
	try {
		return decodeURIComponent(escape(atob($str)));
	} catch (e) {
		console.error("base64Decode failed", e);
	}
}

var jsonEncode = function ($obj) {
	return JSON.stringify($obj);
}

var jsonDecode = function ($json) {
	return JSON.parse($json);
}

var popup = function (options = null) {
	var defaultOptions = $.settings.popupDefaultOptions;
	var $content = false;

	if (typeof options !== "object") {
		var thisOptions = $.settings.popupDefaultOptions;
		thisOptions.content = options;
	} else {
		var thisOptions = { ...$.settings.popupDefaultOptions, ...options };
	}

	consoleLog("popup function fired with options:", options, { level: 0 });

	$.alert(thisOptions);
}

var countMsgsInActiveChat = function () {
	return $("#msgs").find(".message-box").length;
}

var proccessMsgsArr = function (msgs) {
	var $msgs = msgs;

	if (!$msgs || $msgs.length === 0) {
		return false;
	}

	var $i = 0;

	var $msgsHTML = "";

	for (var $thisMsg in $msgs) {

		var $msg = $msgs[$i];

		var $msgId = $msg["row_id"];
		var $msgHTMLId = "msg_id_" + $msg["row_id"]
		var $msgContent = $msg["msg_body"] ?? null;
		var $msgDatetime = $msg["msg_datetime"];
		var $isFromMe = $msg["is_from_me"] ?? 0;
		var $msgType = $msg["msg_type"] ?? null;
		var $isFromMeOrOtherSideCssClass;
		var $msgDirection = "ltr";

		if ($msgContent) {
			if (detectMainLanguage($msgContent) === "hebrew") {
				$msgDirection = "rtl";
			}

			$msgContent = linkifyText($msgContent);
			$msgContent = putPhonesLinks($msgContent);
			$msgContent = newlinesToBr($msgContent);
		}

		if ($isFromMe === 1) {
			$isFromMeOrOtherSideCssClass = "my-message";
		} else {
			$isFromMeOrOtherSideCssClass = "friend-message";
		}

		if ($msgType == "image") {
			$msgContent = "";
			$msgContent += '<img src="' + $msg["msg_body"] + '" />';
		}

		if ($msgType == "e2e_notification") {
			continue;
		}

		if ($msgType == "notification_template") {
			continue;
		}

		if ($msgType == "revoked") {
			$msgContent = "הודעה זו נמחקה";
		}

		if ($msgType == "audio") {
			$msgContent = "";
			$msgContent += '<audio id="' + $msgHTMLId + '" class="audio_msg" controls>';
			$msgContent += '<source src="' + $msg["msg_body"] + '"></source>';
			$msgContent += '</audio>';
		}

		if ($msgType === "video" && $media_file_url) {
			$msgContent = "";
			$msgContent += '<video controls>';
			$msgContent += '<source src="' + $msg["msg_body"] + '" type="video/mp4">';
			$msgContent += '</video>';
		}

		var $elm = "";

		$elm += '<div id="' + $msgHTMLId + '" class="message-box ' + $isFromMeOrOtherSideCssClass + '">';
		$elm += '<p class="content ' + $msgDirection + '">';
		$elm += $msgContent;
		$elm += "<br/>";
		$elm += '<span class="datetime">';
		$elm += $msgDatetime;
		$elm += '</span>';
		$elm += '<span class="msg_id">';
		$elm += $msgId;
		$elm += '</span>'
		$elm += '</p>';
		$elm += '</div>';

		$msgsHTML = $elm + $msgsHTML;
		++$i;
	}

	return $msgsHTML;
}

var playIncommingMsgSound = async function () {
	if ($.settings.playIncommingMsgSound) {
		var audio = new Audio($.settings.incommingMsgSoundUrl);
		audio.play();
	}
}

var loadMsgsFromServerByContactId = async function ($prepend = 0, $contactId = null, $limit = null, $clearChatIfEmpty = 0) {
	consoleLog("loadMsgsFromServerByContactId fired!");

	$.globals.isLoadingMsgs = 1;
	var $contactId = $contactId ?? $.globals.contactId;
	var $username = $.globals.username;

	var $numberOfCurrentMsgs = countMsgsInActiveChat() ?? 0;

	if ($prepend) {
		var $limit = $limit ?? $numberOfCurrentMsgs + "," + $.settings.defaultMsgsLoadingLimiting;
	} else {
		var $limit = $limit ?? $.settings.defaultMsgsLoadingLimiting;
	}

	var loadTriggerHtml = '<div id="load_trigger">🔄</div>';
	var firstMsgId = $("#msgs").find(".message-box").first().attr("id");

	postToServer({
		"route": "get_msgs",
		"data": {
			"username": $username,
			"contact_id": $contactId,
			"limit": $limit,
		},
		"successCallback": function (data) {

			if (!data || data.length == 0) {
				if (!$clearChatIfEmpty) {
					consoleLog("loadMsgsFromServerByContactId returned empty string. That could be because there's no other msgs to load. data: ", data, { level: 3, type: "warn" });
					return false;
				}

				$("#msgs").html(loadTriggerHtml);
				return;
			}

			var $html = proccessMsgsArr(data);

			if ($prepend) {
				$("#msgs").prepend($html);
				$("#msgs").find("#load_trigger").remove();
				$("#msgs").prepend(loadTriggerHtml);
			} else {
				$html = loadTriggerHtml + $html;
				$("#msgs").html($html);

				clearInterval($.intervals.chatUpdateInterval);

				$.intervals.chatUpdateInterval = setInterval(async function () {
					if ($.settings.updateChatWithInterval) {
						loadNewMsgs();
					}
				}, $.settings.chatUpdateInterval);
			}

			$("#msgs audio").each(function () {
				var $this = $(this);
				var $elm_id = $this.attr("id") ?? null;

				var player = new Plyr("#" + $elm_id, {});
				window.player = player;
			});

			var player = new Plyr('audio', {});
			window.player = player;

			var d = $("#msgs");

			if (!$prepend) {
				d.scrollTop(d.prop("scrollHeight"));

				d.on("load", async function () {
					d.scrollTop(d.prop("scrollHeight"));
				});

			} else {
				try {
					if (firstMsgId) {
						document.getElementById(firstMsgId).scrollIntoView({
							behavior: "auto",
							block: "start",
						});


						d.on("load", async function () {
							document.getElementById(firstMsgId).scrollIntoView({
								behavior: "auto",
								block: "start",
							});
						});
					}
				} catch (e) {
					consoleLog(e, { level: 5, type: "error" });
				}

			}

		},
		"onAnywayCallback": function () {
			getLastMsgId();
			$.globals.isLoadingMsgs = 0;
		}
	});
}

var getChats = async function ($append = false, $limit = null, $username = null) {
	var $route = "get_chats";
	var $username = $username ?? $.globals.username ?? null;
	var $limit = $limit ?? $.settings.defaultChatsLoadingLimiting ?? null;

	if (!$username) {
		consoleLog("YOU TRIED TO RUN FUNCTION getChats WITHOUT username", { level: 0 });
		return false;
	}

	postToServer({
		"route": $route,
		"data": {
			"username": $username,
			"limit": $limit,
		},
		"successCallback": function (data) {
			$chats = data;
			consoleLog("chats", $chats, { level: 0 });
			var $i = 0;
			var $allChatsHtml = "";

			for (var $chat in $chats) {
				var $thisChat = $chats[$i];

				var $contactId = $thisChat["contact_id"];
				var $contactName = $thisChat["contact_name"] ?? $thisChat["notify_name"] ?? $contactId ?? null;
				var $profilePicture = $thisChat["profile_picture_url"] ?? $.settings.defaultProfilePicture;
				var $lastMsgDatetime = $thisChat["msg_datetime"] ?? null;
				var $msgTime = $thisChat["msg_datetime"] ?? null;
				var $lastMsgBody = $thisChat["msg_body"] ?? "";
				var $shortLastMsgBody = $lastMsgBody.substring(0, 30) + "...";

				var $contactInformation = {
					"contactName": $contactName,
					"profilePicture": $profilePicture,
				}

				var $jsonStrContactObj = jsonEncode($contactInformation);
				var $encodedContactInformation = base64Encode($jsonStrContactObj);

				var $elm = "";

				$elm += '<div id="' + $contactId + '" class="chat chat-box" data-contactInformation="' + $encodedContactInformation + '" data-contactId="' + $contactId + '" ">';
				$elm += '<div class="img-box contact_profile_img_container">';
				$elm += '<img class="img-cover" src="' + $profilePicture + '" alt="">';
				$elm += '</div>';
				$elm += '<div class="chat-details">';
				$elm += '<div class="text-head">';
				$elm += '<h4>' + $contactName + '</h4>';
				$elm += '<p class="time">' + $msgTime + '</p>';
				$elm += '</div>';
				$elm += '<div class="text-message">';
				$elm += '<p>' + $shortLastMsgBody + '</p>';
				$elm += '</div>';
				$elm += '</div>';
				$elm += '</div>';

				$allChatsHtml += $elm;
				++$i;
			}

			$allChatsHtml += "<div class='load_more_chats'>🔄</div>"

			$("#chats").find(".load_more_chats").remove();

			if (!$append) {
				$("#chats").html($allChatsHtml);
				$("#chats .chat").first().click();
			} else {
				$("#chats").append($allChatsHtml);
			}
		}
	});
}

var getMoreChats = async function () {
	var $currentChatsNum = $("#chats .chat").length;
	var $limit = $currentChatsNum + "," + $.settings.defaultChatsLoadingLimiting;
	getChats(true, $limit);
}

var refreshApp = async function () {
	$.globlas.username = localStorage.getItem("username");
	updateBotsList();
	getChats();
}

var resetAllForms = function () {
	$("body").find("form").each(function () {
		var $this = $(this);
		$this.trigger("reset")
	});

	$(".send_msg_form").removeClass("disabled");
}

var sendTxtMsg = async function ($msg = null, $contactId = null, $username = null, $time = 0) {
	$(".send_msg_form").addClass("disabled");

	if ($.globals.isPendingMsg) {
		consoleLog("you're trying to call sendTxtMsg while another proccess is running", { level: 2, type: "error" });
		return false;
	}

	$.globals.isPendingMsg = 1;

	if (!$.settings.allowSendingMsgs) {
		consoleLog("you're trying to call sendTxtMsg while $.settings.allowSendingMsgs is false", { level: 5, type: "error" });
		$.globals.isPendingMsg = 0;
		return false;
	}

	if (!$msg) {
		consoleLog("you're trying to call sendTxtMsg width empty msg: ", $msg, { level: 5, type: "error" });
		$.globals.isPendingMsg = 0;
		return false;
	}

	var $username = $username ?? $.globals.username;

	if (!$username) {
		$.globals.isPendingMsg = 0;
		console.error("you're trying to send a txt msg without a username");
		return false;
	}

	var $contactId = $contactId ?? $.globals.contactId;

	if (!$contactId) {
		$.globals.isPendingMsg = 0;
		console.error("you're trying to send a txt msg without a contact id");
		return false;
	}

	var postData = {
		msg: $msg,
		username: $username,
		contact_id: $contactId,
		time: $time,
	}

	$.globals.lastTimeSentMsg = Date.now();

	postToServer({
		"data": postData,
		"route": "send_wa_txt_msg",
		"successCallback": function (data) {
			$(".send_msg_form").removeClass("disabled");
			$.globals.isPendingMsg = 0;

			$.globals.lastMsgContent = $msg;
			resetAllForms();
			setTimeout(function () {
				loadMsgsFromServerByContactId();
				$.globals.isPendingMsg = 0;
				$(".send_msg_form").removeClass("disabled");
			}, 250);
		},
		"onAnywayCallback": function () {
			$.globals.isPendingMsg = 0;
			$(".send_msg_form").removeClass("disabled");
		}
	});
}

var getProfilePicByContactId = async function ($contactId = null, $username = null) {
	var $contactId = $contactId ?? $.globals.contactId;
	var $username = $username ?? $.globals.username;

	consoleLog("getProfilePicByContactId fired with $contactId", $contactId, { level: 3 });

	postToServer({
		"route": "get_profile_pic_by_contact_id",
		"data": {
			"contact_id": $contactId,
			"username": $username,
		},
		"successCallback": function (data) {
			try {
				var $url = data?.[0]?.[0] ?? $.settings.defaultProfilePicture;

				$.globals.thisContact.profile_picture_url = $url;
				$(".contact_profile_img img[data-contactId='" + $contactId + "']").attr("src", $url);

				consoleLog($(".contact_profile_img img[data-contactId='" + $contactId + "']"), { level: 0 });

				$("img.contact_profile_img[data-contactId='" + $contactId + "']").attr("src", $url);
				$(".chat[data-contactId='" + $contactId + "'] .contact_profile_img_container img").attr("src", $url);
			} catch (e) {
				consoleLog(e, { level: 5, type: "error" });
			}
		},
		onAnywayCallback: function () {
		}
	});
}

var getContactNameById = async function ($contactId = null, $username = null) {
	var $contactId = $contactId ?? $.globals.contactId;
	var $username = $username ?? $.globals.username;

	consoleLog("getContactNameById fired with $contactId", $contactId, { level: 3 });

	postToServer({
		"route": "get_contact_name_by_contact_id",
		"data": {
			"contact_id": $contactId,
			"username": $username,
		},
		"successCallback": function (data) {
			try {
				var $contactName = data?.[0]?.[0] ?? "";
				$(".contact_name").text($contactName);
				$.globals.contactName = $contactName;
			} catch (e) {
				consoleLog(e, { level: 5, type: "error" });
			}
		},
		onAnywayCallback: function () {
		}
	});
}

var goToChat = async function ($contactId) {
	$(".send_msg_form").removeClass("disabled");

	$("#chat_window .contact_profile_img img").attr("data-contactId", $contactId);

	getProfilePicByContactId($contactId);
	getContactNameById($contactId);

	$(".contact_id").text($contactId);
	$.globals.contactId = $contactId;

	$("#chat_window").addClass("visable");
	loadMsgsFromServerByContactId(false, $contactId, $.settings.defaultMsgsLoadingLimiting, 1);
}

var getLastMsgId = function () {
	var $lastMsgId = $("#msgs .message-box").last().find(".msg_id").text();
	if ($lastMsgId) {
		$.globals.lastMsgId = $lastMsgId;
		return $.globals.lastMsgId;
	}
	return null;
}

var loadNewMsgs = async function ($contactId = null) {
	consoleLog("loadNewMsgs fired!");

	$.globals.isLoadingMsgs = 1;
	var $contactId = $contactId ?? $.globals.contactId;
	var $username = $username ?? $.globals.username;
	var $lastMsgId = getLastMsgId() ?? $.globals.lastMsgId ?? null;

	if (!$lastMsgId) {
		consoleLog("You're trying to call loadNewMsgs but can't figure out what's the lastMsgId: ", $lastMsgId);
		return false;
	}

	postToServer({
		"route": "get_new_msgs",
		"data": {
			"contact_id": $contactId,
			"username": $username,
			"last_id": $lastMsgId,
		},
		"successCallback": function (data) {

			if (!data || data.length == 0) {
				consoleLog("no new msgs", { level: 0 });
				return;
			}

			var $html = proccessMsgsArr(data);

			$("#msgs").append($html);

			$("#msgs audio").each(function () {
				var $this = $(this);
				var $elm_id = $this.attr("id") ?? null;

				var player = new Plyr("#" + $elm_id, {});
				window.player = player;
			});

			var player = new Plyr('audio', {});
			window.player = player;

			var d = $("#msgs");

		},
		"onAnywayCallback": function () {
			getLastMsgId();
			$.globals.isLoadingMsgs = 0;
		}
	});
}

var linkifyText = function (text) {
	var urlRegex = /((https?:\/\/|www\.)[^\s<>"']+)/g;

	var replacedText = text.replace(urlRegex, function (match) {
		var href = match;

		if (!/^https?:\/\//.test(href)) {
			href = 'http://' + href;
		}

		return `<a href="${href}" target="_blank" rel="noopener noreferrer">${match}</a>`;
	});

	return replacedText;
}

var newlinesToBr = function (text) {
	return text.replace(/(\r\n|\n|\r)/g, '<br/>');
}

var detectMainLanguage = function (text) {
	var hebrewMatches = text.match(/[\u0590-\u05FF]/g) || [];
	var englishMatches = text.match(/[a-zA-Z]/g) || [];

	var hebrewCount = hebrewMatches.length;
	var englishCount = englishMatches.length;

	if (hebrewCount > englishCount) {
		return 'hebrew';
	} else if (englishCount > hebrewCount) {
		return 'english';
	} else {
		return 'english';
	}
}

var putPhonesLinks = function (text) {
	var phoneRegex = /\d{9,}/g;

	// נחלק את הטקסט לחלקים: כאלה שעטופים בתגיות a וכאלה שלא
	var parts = text.split(/(<a [^>]*>.*?<\/a>)/g);

	// נעבור על כל החלקים
	for (var i = 0; i < parts.length; i++) {
		// אם זה לא לינק - נעשה החלפה
		if (!parts[i].startsWith('<a ')) {
			parts[i] = parts[i].replace(phoneRegex, function (match) {
				return '<a href="#" class="goToChat" data-contactId="' + match + '@c.us">' + match + '</a>';
			});
		}
	}

	// נחבר את הכל בחזרה
	return parts.join('');
}

var disableMsgsUpdateInterval = function () {
	$.settings.updateChatWithInterval = false;
}

var enableMsgsUpdateInterval = function () {
	$.settings.updateChatWithInterval = false;
}

$(document).ready(function () {
	consoleLog("document ready", { level: 0 });
});

$(window).on("load", function () {

	consoleLog("window loaded", { level: 0 });

	getChats();

	$("body").on("click", ".show_chats_list", function () {
		$("#chat_window").removeClass("visable");
	});

	$("body").on("click", ".goToChat", function () {
		var $contactId = $this.attr("data-contactId");
		$(".contact_id").text($contactId);

		$.globals.contactId = $contactId;

		$("#chat_window").addClass("visable");

		loadMsgsFromServerByContactId(false);
	})

	$("body").on("click", "#chats .chat", function () {
		var $this = $(this);

		$("#chats .chat").removeClass("active");
		$this.addClass("active");

		var $encodedContactInformation = $this.attr("data-contactInformation") ?? null;

		if ($encodedContactInformation) {
			$.globals.thisContact = jsonDecode(base64Decode($encodedContactInformation));
		}

		var $profilePicture = $.globals.thisContact?.profile_picture_url ?? $.settings.defaultProfilePicture ?? null;
		var $contactName = $.globals.thisContact?.name ?? $.globals.thisContact?.notify_name ?? null;

		$(".contact_profile_img img").attr("src", $profilePicture)
		$(".contact_name_container .contact_name").text($contactName);

		var $contactId = $this.attr("id");
		consoleLog($contactId, { level: 0 });

		goToChat($contactId);
	});

	$("body").on("click", ".user_avatar_container, .contact_profile_img", async function () {
		getProfilePicByContactId();
		var $imgUrl = $(this).find("img").attr("src");
		var $imgTag = "<img class='contact_profile_img' src='" + $imgUrl + "' />";

		popup($imgTag);
	});

	$("body").on("click", ".message-box .content img", function () {

		var $this = $(this);
		var $img_url = $this.attr("src");
		var $img_tag = "<img class='full_height_img' src='" + $img_url + "' />";

		popup($img_tag);
	});

	$("body").on("click", "#load_trigger", function () {
		loadMsgsFromServerByContactId(true);
	});

	$("body").on("click", ".logout", async function () {
		popup({
			"content": "Are you sure you wanna logout?",
			buttons: {
				yes: {
					text: "Yes",
					action: function () {
						popup("Logout function comes here!");
					}
				},
				no: {
					text: "No",
					action: function () { }
				}
			}
		});
	});

	$("body").on("submit", "#send_msg", function (e) {
		e.preventDefault();
		var $msg = $("#send_msg #msg").val();
		if (!$(this).hasClass("disabled")) {
			sendTxtMsg($msg);
		}
	});

	$("body").on("click", ".load_more_chats", function () {
		getMoreChats();
	});

});