/*
* SYSTEM INACTIVATION TIMEOUT
* Automatic logout when user is inactive for determinate period.
*/
var logoutTimer = $('meta[name=logoutTimer]').attr('content');
var barwidth = 100;

const renewUserSessionUrl = "/user-login/renew-user-session";
const checkLogoutStatusUrl = "/user-login/check-logout-status";
const testLogoutModal = "/user-login/test-logout-modal";
const intervalTime = 1000;

const getCookies = (name) => {
    return document.cookie.split('; ').reduce((r, v) => {
        const parts = v.split('=')
        return parts[0] === name ? decodeURIComponent(parts[1]) : r
    }, '')
}

// Check if cookie contains index -> 'userSession' and value '1' if user is logged and 0 when user in not logged
var cookieString = getCookies('userSession');
var termsUserLogged = cookieString.includes('"1";}') && cookieString.includes("userSession");

const logoutCountdown = setInterval(() => {
    if (termsUserLogged) {
        // TODO: delete this
        console.log(logoutTimer);
        $('input, select, textarea').change(() => {
            logoutTimer = $('meta[name=logoutTimer]').attr('content');
            const req = $.ajax({
                type: 'POST',
                url: renewUserSessionUrl,
                data: {'RenewlogoutTimer': 'RenewlogoutTimer'},
                success: function (response) {
                    var jsonData = JSON.parse(response);
                    if ((Object.keys(jsonData).length >= 1) && (jsonData != null)) {
                        jsonData.renewCounterValue = logoutTimer;
                    } else {
                        logoutTimer = $('meta[name=logoutTimer]').attr('content');
                    }
                },
                error: function (xhr) {
                    console.error("An error occurred: " + xhr.status + " " + xhr.statusText);
                }
            });

        });

        // TODO: parametrize value of logoutTimer
        // TODO: preguntar cuanto va durar la session inactiva
        $("#logoutTimerBar").css('width', logoutTimer + '%');
        if (logoutTimer === 55) {
            console.log("You will be logout in 55 seconds!!!");
        }
        if (logoutTimer === 50) {
            console.log("You will be logout in 50 seconds!!!");
        }
        if (logoutTimer === 30) {
            $("#progressloginBar").show(250);
        }
        if (logoutTimer <= 30) {
            barwidth = logoutTimer * 3;
            $("#logoutTimerBar").css({'width': barwidth + '%', 'background-color': 'red',})
                .text(`Your session will be disconnected in: ${logoutTimer} sec`);
        }
        if (logoutTimer === 10) {
            // check user
            const request = $.post(
                checkLogoutStatusUrl,
                {'logoutTimer': 'logoutTimer'},
                function (data) {
                    if ((data.length >= 1 || jQuery.trim(data) === 'logout')) {
                        //logoutUser
                        window.location.href = "/site/loginemail";
                        window.location.replace("/site/loginemail");
                    } else {
                        logoutTimer = data;
                    }
                }
            );
            request.fail(function (xhr) {
                console.error("An error occured: " + xhr.status + " " + xhr.statusText);
            });
        }
        if (logoutTimer === 0) {
            const request = $.post(
                checkLogoutStatusUrl,
                {'logoutTimer': 'logoutTimer'},
                function (data) {
                    if ((data.length >= 1 || jQuery.trim(data) === 'logout')) {
                        //logoutUser
                        window.location.href = "/site/loginemail";
                        window.location.replace("/site/loginemail");
                    } else {
                        logoutTimer = data;
                    }
                }
            );
            request.fail(function (xhr) {
                console.error("An error occured: " + xhr.status + " " + xhr.statusText);
            });
            clearInterval(logoutCountdown);
        }
    }
    logoutTimer--;
}, intervalTime);

// *************** TESTS
//setInterval(check_user, 2000);
// check if sesson has expire
/*function check_user () {
    $.ajax({
        url: checkLogoutStatusUrl,
        method:'POST',
        data:'type=logout',
        success:function(result) {
            if (result === 'logout'){
                $("#session-expire-warning-modal").modal({
                    backdrop: 'static',
                    keyboard: false,
                });
                setTimeout(function(){
                    $('#session-expire-warning-modal').modal('hide');
                        window.location.href = "/site/loginemail";
                }, 10000);
            }
        }
    });
}*/
/*const logoutCountdownModal = setInterval( () => {
    if (termsUserLogged){
        console.log(logoutTimer);
        $('input, select, textarea').change(() => {
            logoutTimer = $('meta[name=logoutTimer]').attr('content');
            const req = $.ajax({
                type: 'POST',
                url: renewUserSessionUrl,
                data: {'RenewlogoutTimer': 'RenewlogoutTimer'},
                success: function (response) {
                    var jsonData = JSON.parse(response);
                    if ((Object.keys(jsonData).length >= 1) && (jsonData != null)) {
                        jsonData.renewCounterValue = logoutTimer;
                    } else {
                        logoutTimer = $('meta[name=logoutTimer]').attr('content');
                    }
                },
                error: function (xhr) {
                    console.error("An error occurred: " + xhr.status + " " + xhr.statusText);
                }
            });

        });

        if (logoutTimer === 0){
            $("session-expire-warning-modal").modal('show');
            clearInterval(logoutCountdownModal);
        }
    }
    logoutTimer--;
},1000);*/
