/*! WireKit per page v2.73.0 | MIT License | https://wirekit.app | bundled packages: wirekit.auto.js */
var e=` 	
\r\0\v`;function i(n){let r=0,t=n.length;for(;r<t&&e.includes(n[r]);)r++;for(;t>r&&e.includes(n[t-1]);)t--;return n.slice(r,t)}function s(n){if(typeof n=="boolean")return n;if(typeof n=="number")return Number.isNaN(n)?null:n!==0;if(typeof n!="string")return null;let r=i(n).toLowerCase();return["1","true","on","yes"].includes(r)?!0:["0","false","off","no"].includes(r)?!1:null}export{i as a,s as b};
