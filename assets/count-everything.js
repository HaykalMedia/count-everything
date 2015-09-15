var request = new XMLHttpRequest(),
    countAction = (typeof(countEverything) !== "undefined" ? countEverything.countAction : aliqtisadi.countAction),
    postID = (typeof(countEverything) !== "undefined" ? countEverything.postID : aliqtisadi.postID),
    ajaxurl = (typeof(countEverything) !== "undefined" ? countEverything.ajaxurl : aliqtisadi.ajaxurl);

var data = "action=" + countAction +
    "&post_id=" + postID;
request.open('POST', ajaxurl, true);
request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8', true);
request.send(data);
