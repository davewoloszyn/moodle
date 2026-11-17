var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * A banner nudging teachers to set up learning outcomes in their course, which can optionally be dismissed.
 *
 * @module     core_course/LearningOutcomesAlert
 * @copyright  2026 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Alert, Link } from "@moodlehq/design-system";
import { fetchOne } from "@moodle/lms/core/ajax";
function LearningOutcomesAlert({
  message,
  preference,
  dismisslabel,
  linklabel,
  linkurl
}) {
  const handleDismiss = /* @__PURE__ */ __name(() => {
    if (!preference) {
      return;
    }
    fetchOne({
      methodname: "core_user_update_user_preferences",
      args: { preferences: [{ type: preference, value: "1" }] }
    }).catch(() => void 0);
  }, "handleDismiss");
  return /* @__PURE__ */ jsxDEV(
    Alert,
    {
      type: "info",
      message,
      isDismissible: Boolean(preference),
      dismissAriaLabel: dismisslabel,
      onDismiss: handleDismiss,
      className: "my-3",
      children: linklabel && linkurl && /* @__PURE__ */ jsxDEV(Link, { label: linklabel, href: linkurl }, void 0, false, {
        fileName: "public/course/js/esm/src/LearningOutcomesAlert.tsx",
        lineNumber: 76,
        columnNumber: 38
      }, this)
    },
    void 0,
    false,
    {
      fileName: "public/course/js/esm/src/LearningOutcomesAlert.tsx",
      lineNumber: 68,
      columnNumber: 9
    },
    this
  );
}
__name(LearningOutcomesAlert, "LearningOutcomesAlert");
export {
  LearningOutcomesAlert as default
};
//# sourceMappingURL=LearningOutcomesAlert.dev.js.map
