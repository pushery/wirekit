/*! WireKit per page v2.72.0 | MIT License | https://wirekit.app | bundled packages: wirekit.auto.js */
function r(e){return!!e?.isComposing||e?.keyCode===229}function i(e){r(e)&&e.stopImmediatePropagation()}function s(e){e.directive("wk-ime",(o,n,{cleanup:t})=>{o.addEventListener("keydown",i,{capture:!0}),t(()=>o.removeEventListener("keydown",i,{capture:!0}))})}export{r as a,s as b};
