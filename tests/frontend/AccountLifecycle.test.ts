import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import AccountLifecycle from '../../resources/js/pages/x-change/cockpit/AccountLifecycle.vue';

const { submissions } = vi.hoisted(() => ({ submissions: [] as string[] }));
vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<span />' },
    router: { get: vi.fn() },
    useForm: (data: { status: string; reason: string }) => {
        const form = reactive({ ...data, processing: false, errors: {} });
        return Object.assign(form, { post: () => submissions.push(form.status), reset: () => {} });
    },
}));
vi.mock('@/cockpit/layouts/CockpitLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }));

it('submits the action matching the latest account status after an Inertia update', async () => {
    submissions.length = 0;
    const account = { id: '2', name: 'Test', status: 'active', events: [] };
    const wrapper = mount(AccountLifecycle, { props: { account } });
    await wrapper.findAll('form')[1].trigger('submit');
    await wrapper.setProps({ account: { ...account, status: 'suspended' } });
    await wrapper.findAll('form')[1].trigger('submit');
    expect(submissions).toEqual(['suspended', 'active']);
});
