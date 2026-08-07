import { defineComponent, h } from "vue";

function passthrough(name: string) {
  return defineComponent({
    name,
    setup(_, { slots }) {
      return () => h("div", { "data-select-part": name }, slots.default?.());
    },
  });
}

export const Select = passthrough("Select");
export const SelectContent = passthrough("SelectContent");
export const SelectGroup = passthrough("SelectGroup");
export const SelectLabel = passthrough("SelectLabel");
export const SelectItem = passthrough("SelectItem");
export const SelectTrigger = passthrough("SelectTrigger");
export const SelectValue = defineComponent({
  name: "SelectValue",
  props: { placeholder: String },
  setup(props) {
    return () => h("span", props.placeholder);
  },
});
