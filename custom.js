/*
* SYSTEM INACTIVATION TIMEOUT
* Automatic logout when user is inactive for determinate period.
*/
var logoutTimer = $('meta[name=logoutTimer]').attr('content');
var barwidth = 100;

var absLogoutTimer = 60;
var logoutTimer = 60;
const modalTimeDelay = 5;
const checkLogoutStatusUrl = "/user/check-logout-status";
const renewSessionUrl = "/user-login/renew-user-session";
const intervalTime = 1000;


const getCookies = (name) => {
    return document.cookie.split('; ').reduce((r, v) => {
        const parts = v.split('=')
        return parts[0] === name ? decodeURIComponent(parts[1]) : r
    }, '')
}
function showModal() {
    $('#logoutModal').modal('show');
}
function closeModal() {
    $('#logoutModal').modal('hide');
    //$('#logoutModal').modal('toggle');
}
/*function requestCheckLogoutStatus() {
    var request = $.post(
        checkLogoutStatusUrl,
        {'logoutTimer': 'logoutTimer'},
        function (res) {
            if ((res.length >= 1 || jQuery.trim(res) === 'logout')) {
                //logoutUser
                window.location.href = "/site/loginemail";
                window.location.replace("/site/loginemail");
            } else {
                return logoutTimer = res;
            }
        }
    );
    request.done(function (data) { console.log('Jquery post request successfully'); });
    request.fail(function (data) { console.error("Jquery post request failed"); });
}
function requestRenewSession() {
    //!* ajax request to renew session
    $.ajax({
        url: renewSessionUrl,
        type: 'POST',
        data: {'RenewlogoutTimer': 'RenewlogoutTimer'},
        success: function(res) {
            var jsonData = JSON.parse(res);
            if ((Object.keys(jsonData).length >= 1) && (jsonData != null)) {
                return jsonData.renewCounterValue = logoutTimer;
            }else {
                return logoutTimer = $('meta[name=logoutTimer]').attr('content');
            }
        },
        error: function(xhr){
            console.error("An error occurred: " + xhr.status + " " + xhr.statusText);
        }
    });
}
function requestDirectLogout(){
    var requestdirectLogout = $.post(
        "/user/check-logout-status",
        {'directLogout': 'directLogout'},
        function (data) {
            console.log(data);
            if ((data.length >= 1 || jQuery.trim(data) === 'logout')) {
                //logoutUser redirect to login page
                window.location.href = "/site/loginemail";
                window.location.replace("/site/loginemail");
            }
        }
    );
    // TODO: Delete this console.log
    requestdirectLogout.done(function (data) { console.log('Jquery post request successfully'); });
    requestdirectLogout.fail(function (data) { console.log("Jquery post request failed"); });
}*/


// Check if cookie contains index -> 'userSession' and value '1' if user is logged and 0 when user in not logged
var cookieString = getCookies('userSession');
var termsUserLogged = cookieString.includes('"1";}') && cookieString.includes("userSession");

// with progression bar
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

// with modal
const InactiveLogoutCountdown = setInterval(() => {
    if (!cookieString ){ // check cookie in case the user decides to delete it manually
        //TODO: ajax request to direct logout
        // request to logout
        var requestdirectLogout = $.post(
            "/user/check-logout-status",
            {'directLogout': 'directLogout'},
            function (data) {
                console.log(data);
                if ((data.length >= 1 || jQuery.trim(data) === 'logout')) {
                    //logoutUser redirect to login page
                    window.location.href = "/site/loginemail";
                    window.location.replace("/site/loginemail");
                }
            }
        );
        // TODO: Delete this console.log
        requestdirectLogout.done(function (data) { console.log('Jquery post request successfully'); });
        requestdirectLogout.fail(function (data) { console.log("Jquery post request failed"); });

        clearInterval(InactiveLogoutCountdown);
    }
    // if exists cookie
    if (termsUserLogged){
        console.log(logoutTimer);
        // event on change form
        $('input, select, textarea').change( () => {
            logoutTimer = $('meta[name=logoutTimer]').attr('content');
            const req = $.ajax({
                type: 'POST',
                url: '/user/renew-user-session',
                data: {'RenewlogoutTimer': 'RenewlogoutTimer'},
                success: function (response) {
                    var jsonData = JSON.parse(response);
                    if ((Object.keys(jsonData).length >= 1) && (jsonData != null)){
                        jsonData.renewCounterValue = logoutTimer;
                    }else{
                        logoutTimer = $('meta[name=logoutTimer]').attr('content');
                    }
                },
                error: function(xhr){
                    console.error("An error occured: " + xhr.status + " " + xhr.statusText);
                }
            });

        });

        //* show modal past 5s --> modalTimeDelay = 5
        if (logoutTimer === (absLogoutTimer - modalTimeDelay)) {
            showModal();
            // capture click on renew session button
            $('#logoutModal').on('click', '#renew-session-button', function(){
                console.log('renew-session-button');
                //! ajax request to renew session
                const reqRenewSession = $.ajax({
                    type: 'POST',
                    url: '/user/renew-user-session',
                    data: {'RenewlogoutTimer': 'RenewlogoutTimer'},
                    success: function (response) {
                        var jsonData = JSON.parse(response);
                        if ((Object.keys(jsonData).length >= 1) && (jsonData != null)){
                            jsonData.renewCounterValue = logoutTimer;
                        }else{
                            logoutTimer = $('meta[name=logoutTimer]').attr('content');
                        }
                    },
                    error: function(xhr){
                        console.error("An error occured: " + xhr.status + " " + xhr.statusText);
                    }
                });
                closeModal();
            });
            // capture click on logout button
            $('#logoutModal').on('click', '#logout-button', function(){
                console.log('logout-button');
                //TODO: request to logout
                var request = $.post(
                    "/user/check-logout-status",
                    {'logoutTimer': 'logoutTimer'},
                    function (data) {
                        console.log(data);
                        if ((data.length >= 1 || jQuery.trim(data) === 'logout')) {
                            //logoutUser
                            //! close modal????
                            window.location.href = "/site/loginemail";
                            window.location.replace("/site/loginemail");
                        } else {
                            logoutTimer = data;
                        }
                    }
                );
                // TODO: delete this
                request.done(function () { console.log('Jquery post request successfully'); });
                request.fail(function () { console.log("Jquery post request failed"); });

                clearInterval(InactiveLogoutCountdown);
                //! close modal???
                closeModal();
            });

        }

        //no interaction with the logout modal when the time is 0 auto logout
        if (logoutTimer === 0) {
            //! ajax request to logout
            var request = $.post(
                "/user/check-logout-status",
                {'logoutTimer': 'logoutTimer'},
                function (data) {
                    console.log(data);
                    if ((data.length >= 1 || jQuery.trim(data) === 'logout')) {
                        //logoutUser
                        //! close modal????
                        window.location.href = "/site/loginemail";
                        window.location.replace("/site/loginemail");
                    } else {
                        logoutTimer = data;
                    }
                }
            );
            request.done(function (data) { console.log('Jquery post request successfully'); });
            request.fail(function (data) { console.log("Jquery post request failed"); });

            //¿close modal???
            clearInterval(InactiveLogoutCountdown);
        }

    }
    logoutTimer--;
}, 1000);


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
