console.log('main.js loaded successfully');

// TODO: DELETE conts in UserController.
// TODO: DELETE conts in SiteController.
// TODO: DELETE methods actionCheckLogoutStatus, actionRenewUserSession in UserController


var logoutTimer = $('meta[name=logoutTimer]').attr('content');
var absLogoutTimer = 60;
var logoutTimer = 60;
const modalTimeDelay = 5;
const checkLogoutStatusUrl = "/user/check-logout-status"; 
const renewSessionUrl = "/user-login/renew-user-session";

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

function renewLogoutTimer() {
    return $('meta[name=logoutTimer]').attr('content');
    //return logoutTimer = 60;
}

function requestCheckLogoutStatus() {
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
    //* ajax request to renew session
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

function requestOnChangeForm() {
    const req = $.ajax({
        type: 'POST',
        url: '/user/renew-user-session',
        data: {'RenewlogoutTimer': 'RenewlogoutTimer'},
        success: function (response) {
            var jsonData = JSON.parse(response);
            if ((Object.keys(jsonData).length >= 1) && (jsonData != null)){
                return jsonData.renewCounterValue = logoutTimer;
            }else{
                return logoutTimer = $('meta[name=logoutTimer]').attr('content');
            }
        },
        error: function(xhr){
            console.error("An error occured: " + xhr.status + " " + xhr.statusText);
        }
    });
}


//TODO: more clean test this
const logoutCountdown = setInterval(() => {
    console.log(logoutTimer); 
    if (termsUserLogged) {
        //! Event on change forms
        $('input, select, textarea').change( () => {
            // logoutTimer = $('meta[name=logoutTimer]').attr('content');  
            //! Request on change forms
            var requestOnChangeForm =  requestOnChangeForm();
            // logoutTimer = requestOnChangeForm;
            logoutTimer = $('meta[name=logoutTimer]').attr('content');

        });

    //* show modal past 5s --> modalTimeDelay = 5
    if(logoutTimer === (absLogoutTimer - modalTimeDelay)) {
        showModal();
        $('#logoutModal').on('click', '#renew-session-button', function(){
            console.log('renew-session-button');
            //! ajax request to renew session
            requestRenewSession();
            renewLogoutTimer();
            closeModal();
       });

       $('#logoutModal').on('click', '#logout-button', function(){
            console.log('logout-button');
            //TODO: ajax request to logout
            requestCheckLogoutStatus();
            //! close modal
            closeModal();
       });

       $('#logoutModal').on('click', '#cancel-button', function(){
            console.log('cancel-button');
        });
    }
    
    if (logoutTimer === 0) {
        //TODO:post request logout
        clearInterval(logoutCountdown);
        //! close modal
        $('#logoutModal').modal('toggle'); 
        //$('#logoutModal').modal().hide();
    }      
    }
    
    logoutTimer--;
}, 1000);   


var cookieString = getCookies('userSession');
var termsUserLogged = cookieString.includes('"1";}') && cookieString.includes("userSession");

const InactiveLogoutCountdown = setInterval(() => {
    if (!cookieString ){ // check cookie in case the user decides to delete it manually
        //TODO: ajax request to direct logout
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

        clearInterval(logoutCountdown);
    }
    // exists cookie
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

            $('#logoutModal').on('click', '#logout-button', function(){
                console.log('logout-button');
                //TODO: ajax request to logout
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

                clearInterval(logoutCountdown);
                //! close modal???
                closeModal();
            });

            /*$('#logoutModal').on('click', '#cancel-button', function(){
                console.log('cancel-button');
            });*/
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

            clearInterval(logoutCountdown);
        }

    }
    logoutTimer--;
}, 1000);   


