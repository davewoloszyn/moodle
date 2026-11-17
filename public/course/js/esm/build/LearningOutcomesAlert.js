import{Alert as a,Link as o}from"@moodlehq/design-system";import{fetchOne as m}from"@moodle/lms/core/ajax";import{jsx as i}from"react/jsx-runtime";/**
 * A banner nudging teachers to set up learning outcomes in their course, which can optionally be dismissed.
 *
 * @module     core_course/LearningOutcomesAlert
 * @copyright  2026 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */function l({message:n,preference:e,dismisslabel:t,linklabel:s,linkurl:r}){return i(a,{type:"info",message:n,isDismissible:!!e,dismissAriaLabel:t,onDismiss:()=>{e&&m({methodname:"core_user_update_user_preferences",args:{preferences:[{type:e,value:"1"}]}}).catch(()=>{})},className:"my-3",children:s&&r&&i(o,{label:s,href:r})})}export{l as default};
