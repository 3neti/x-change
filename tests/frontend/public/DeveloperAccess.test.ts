import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, expect, it, vi } from 'vitest';
import DeveloperAccess from '../../../resources/js/pages/x-change/public/DeveloperAccess.vue';

vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<div><slot /></div>' } }));

afterEach(() => vi.unstubAllGlobals());

it('renders operations from the published reference and distinguishes public MCP', async () => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ paths: { '/pay-codes': { post: { summary: 'Issue a Pay Code', security: [{ partnerOAuth: ['pay-codes:issue'] }] } }, '/account-funded-orders': { post: { summary: 'Create an account-backed Pay Code funding order', security: [{ partnerOAuth: ['funded-orders:create', 'pay-codes:issue'] }], requestBody: { content: { 'application/json': { examples: { payable: { value: { voucher_type: 'payable', target_amount: 100 } } } } } } } }, '/funded-orders/{reference}': { get: { summary: 'Poll a client-owned funded order', security: [{ partnerOAuth: ['funded-orders:read'] }] } } } }) }));
  const wrapper = mount(DeveloperAccess, { props: { access_contact: 'api@example.test', spec_url: '/api/partner/openapi.json', partner_enabled: true, mcp_enabled: true, public_mcp_enabled: true }, global: { stubs: { Head: true } } });
  await flushPromises();
  expect(wrapper.text()).toContain('Issue a Pay Code');
  expect(wrapper.text()).toContain('pay-codes:issue');
  expect(wrapper.text()).toContain('Create an account-backed Pay Code funding order');
  expect(wrapper.text()).toContain('Poll a client-owned funded order');
  expect(wrapper.text()).toContain('payable example');
  expect(wrapper.text()).toContain('target_amount');
  expect(wrapper.text()).toContain('separate from a customer');
  expect(wrapper.text()).toContain('read-only discovery');
  expect(wrapper.text()).toContain('/api/x/v1/public-issuance/handoff');
  expect(wrapper.text()).toContain('do not create an order or issue a Pay Code');
  expect(wrapper.find('a[href="mailto:api@example.test"]').exists()).toBe(true);
});

it('explains when the specification cannot be loaded', async () => {
  vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
  const wrapper = mount(DeveloperAccess, { props: { access_contact: null, spec_url: '/api/partner/openapi.json', partner_enabled: false, mcp_enabled: false, public_mcp_enabled: false }, global: { stubs: { Head: true } } });
  await flushPromises();
  expect(wrapper.text()).toContain('specification is unavailable');
  expect(wrapper.text()).toContain('Contact your X-Change operator');
});

it('copies an issuance example with the required business reference', async () => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ paths: {} }) }));
  const writeText = vi.fn().mockResolvedValue(undefined);
  Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });
  const wrapper = mount(DeveloperAccess, { props: { access_contact: null, spec_url: '/api/partner/openapi.json', partner_enabled: true, mcp_enabled: true, public_mcp_enabled: false }, global: { stubs: { Head: true } } });
  await wrapper.findAll('button')[2].trigger('click');
  expect(writeText).toHaveBeenCalledWith(expect.stringContaining('external_reference'));
  expect(wrapper.text()).toContain('Copied');
});
