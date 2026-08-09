import { h } from "vue";

const passthrough = (name: string) => ({
  name,
  setup(
    _props: unknown,
    { slots }: { slots: Record<string, () => unknown[]> },
  ) {
    return () => h("div", slots.default?.());
  },
});

export const Dialog = {
  name: "Dialog",
  props: ["open"],
  emits: ["update:open"],
  setup(
    props: { open?: boolean },
    { slots }: { slots: Record<string, () => unknown[]> },
  ) {
    return () =>
      props.open ? h("div", { role: "dialog" }, slots.default?.()) : null;
  },
};

export const DialogContent = passthrough("DialogContent");
export const DialogDescription = passthrough("DialogDescription");
export const DialogFooter = passthrough("DialogFooter");
export const DialogHeader = passthrough("DialogHeader");
export const DialogTitle = passthrough("DialogTitle");
