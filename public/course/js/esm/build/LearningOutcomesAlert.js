import{Alert as m,Link as o}from"@moodlehq/design-system";import{fetchOne as d}from"@moodle/lms/core/ajax";import{jsx as r}from"react/jsx-runtime";/**
 * A banner nudging teachers to set up learning outcomes in their course, which can optionally be dismissed.
 *
 * @module     core_course/LearningOutcomesAlert
 * @copyright  2026 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */function f({message:t,preference:e,dismisslabel:a,linklabel:s,linkurl:i}){const n=e!==void 0&&e!=="";return r(m,{type:"info",message:t,isDismissible:n,dismissAriaLabel:a,onDismiss:()=>{n&&d({methodname:"core_user_update_user_preferences",args:{preferences:[{type:e,value:"1"}]}}).catch(()=>{})},className:"my-3",children:s!==void 0&&s!==""&&i!==void 0&&i!==""&&r(o,{label:s,href:i})})}export{f as default};
