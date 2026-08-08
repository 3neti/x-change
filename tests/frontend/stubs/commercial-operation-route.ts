function storeUrl(id?: number | string) {
  return id === undefined
    ? "/x/cockpit/commercial"
    : `/x/cockpit/commercial/${id}`;
}

export const store = Object.assign(storeUrl, { url: storeUrl });
