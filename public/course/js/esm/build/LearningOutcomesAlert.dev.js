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
  const isDismissible = preference !== void 0 && preference !== "";
  const hasLink = linklabel !== void 0 && linklabel !== "" && linkurl !== void 0 && linkurl !== "";
  const handleDismiss = /* @__PURE__ */ __name(() => {
    if (!isDismissible) {
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
      isDismissible,
      dismissAriaLabel: dismisslabel,
      onDismiss: handleDismiss,
      className: "my-3",
      children: hasLink && /* @__PURE__ */ jsxDEV(Link, { label: linklabel, href: linkurl }, void 0, false, {
        fileName: "public/course/js/esm/src/LearningOutcomesAlert.tsx",
        lineNumber: 80,
        columnNumber: 25
      }, this)
    },
    void 0,
    false,
    {
      fileName: "public/course/js/esm/src/LearningOutcomesAlert.tsx",
      lineNumber: 72,
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
