/*! WireKit per page v2.73.0 | MIT License | https://wirekit.app | bundled packages: wirekit.auto.js */
function n(l){let e=l?.parentElement??null;for(;e&&e!==document.body&&e!==document.documentElement;){let t=getComputedStyle(e).overflowY;if((t==="auto"||t==="scroll")&&e.scrollHeight>e.clientHeight)return e;e=e.parentElement}return null}export{n as a};
