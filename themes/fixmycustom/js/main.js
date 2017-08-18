
window.onload = function () {
      load();
}

function load() {

    if ($("map_overlay_bignumber_slider")) { // große nummern initialiseren
        bignumberInit();
    }    
    
}

/* SIDEBAR =============================================================================== */

/* Big Number ———————————————————————————————————————— */

var current_big_number_index=0;

function bignumberInit() {
    window.setInterval(bignumberStep, 5000);
}

function bignumberStep() {

    console.log("step");
    
    $("map_overlay_bignumber_slider").style.transform="translate3d(-"+(current_big_number_index*25)+"%,0px,0)";

    current_big_number_index++;
    if (current_big_number_index>=4) {
        current_big_number_index=0;
    }
    
}

/* Voting ———————————————————————————————————————————— */

var is_voting_extended=false;

function votingAction(element) {
    if (is_voting_extended) {
        votingCollapse();
    } else {
        votingExtend(element);
    }
}

function votingExtend(element) {

    var current_voting_type=element.id.split("map_overlay_voting_container_")[1];
    
    if (current_voting_type!="gut") { // alle ausser gut werden extended
       // alert(current_voting_type);   
       
        // voting container extenden
        $("map_overlay_voting").className="map_overlay_voting_extended";
       
        // voting container ausblenden und verschieben
        var voting_container_elements=document.getElementsByClassName("map_overlay_voting_container");
        for (var i=0;i<voting_container_elements.length;i++) {
            if (voting_container_elements[i]==element) { // das aktuelle element
                voting_container_elements[i].style.left="0px";
               // voting_container_elements[i].style.width="200px";
            } else { // alle anderen elemente
                voting_container_elements[i].style.opacity=0;
            }
        }
        
        // feedback container einblenden
        $("map_overlay_voting_feedback").style.display="flex";
        window.setTimeout('$("map_overlay_voting_feedback").style.opacity=1;',0);
        
        // richtige überschrift anzeigen
        $("map_overlay_voting_feedback_title_"+current_voting_type).style.display="block";
        $("map_overlay_voting_feedback_field").placeholder=$("map_overlay_voting_feedback_placeholder_"+current_voting_type).innerText;
        
        is_voting_extended=true;
    
    }
   
}

function votingCollapse() {

    // voting container schrumpfen
    $("map_overlay_voting").className="";
       
    // voting container anzeigen und verschieben
    var voting_container_elements=document.getElementsByClassName("map_overlay_voting_container");
    for (var i=0;i<voting_container_elements.length;i++) {
        voting_container_elements[i].style.left="";
        voting_container_elements[i].style.opacity=1;
    }
    
    // feedback container ausblenden
    $("map_overlay_voting_feedback").style.opacity=0;
    window.setTimeout('$("map_overlay_voting_feedback").style.display="none";',200);
    
    is_voting_extended=false;

}


// helper —————————————————————————————————————————————————————————————————————————————————————————————————————

function zufall(a,b) {
    return Math.floor(Math.random()*(b-a+1))+a;
}

function $(id) {
    return document.getElementById(id);
}

function post(url, callback_function, fail_function, callback_id, parameter){
try {

 //   parameter.push(["random",Math.random()]);
    for (var i=0;i<parameter.length;i++) { // alle parameter values uri encodieren
        parameter[i][1]=encodeURIComponent(parameter[i][1]);
        parameter[i]=parameter[i].join("=");
    }
    parameter=parameter.join("&");
    
    var http_request = new XMLHttpRequest();
    http_request.overrideMimeType('text/xml; charset=UTF-8');
    
    http_request.onreadystatechange = function() {
   // alert(http_request.status);
        if (http_request.readyState == 4) {
            if (http_request.status == 200) {
                if (callback_function!=null&&callback_id!=null) {
                    window[callback_function](callback_id,http_request.responseText);
                } else if (callback_function!=null) {
                    window[callback_function](http_request.responseText);
                }
            } else {//if (http_request.status!=0) {
              //  alert("Fehler. "+http_request.status+". "+http_request.responseText);
                
                if (fail_function!=null&&callback_id!=null) {
                    window[fail_function](callback_id,http_request.status);
                } else if (fail_function!=null) {
                    window[fail_function](http_request.status);
                }

            }
        }
    }
    
    //  http_request.onprogress = function(event) {
    //      if (event.lengthComputable) {
    //          $("navigation_progress").style.webkitTransform="translate3d("+ ( -70+   ((event.loaded/event.total)*70)    )+"%,0px,0)";
    //      }
    //  }
    
    http_request.open('POST', url,true);
    http_request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    http_request.send(parameter);
    
} catch(e) {
    console.log("AJAX. ");
}

}
