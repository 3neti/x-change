export function store() {
  return { url: "/x/cockpit/accounts/bank-accounts", method: "post" };
}

export function update(assignment: number) {
  return { url: `/x/cockpit/accounts/bank-accounts/${assignment}`, method: "patch" };
}
