<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BadgePlus,
    BookOpen,
    CircleGauge,
    FileStack,
    Landmark,
    Megaphone,
    RadioTower,
    Scale,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import {
    dashboard,
    documentation,
    quickGenerate,
} from '@/routes/x-change/cockpit';
import accounts from '@/routes/x-change/cockpit/accounts';
import campaigns from '@/routes/x-change/cockpit/campaigns';
import commercial from '@/routes/x-change/cockpit/commercial';
import diagnostics from '@/routes/x-change/cockpit/diagnostics';
import funding from '@/routes/x-change/cockpit/funding';
import payCodes from '@/routes/x-change/cockpit/pay-codes';
import type { NavItem } from '@/types';

// X-CHANGE HOST SHELL · package-owned navigation, account controls remain host-owned.
type XChangeNavigationItem = NavItem & {
    description: string;
};

const workspaceItems: XChangeNavigationItem[] = [
    {
        title: 'Overview',
        description: 'Funds, capacity, and activity',
        href: dashboard(),
        icon: CircleGauge,
    },
    {
        title: 'Issuance',
        description: 'Design and issue a Pay Code',
        href: quickGenerate(),
        icon: BadgePlus,
    },
    {
        title: 'Funding',
        description: 'Add and confirm funds',
        href: funding.index(),
        icon: Landmark,
    },
    {
        title: 'Pay Codes',
        description: 'Find and manage Pay Codes',
        href: payCodes.index(),
        icon: FileStack,
    },
    {
        title: 'Campaigns',
        description: 'Issue to many recipients',
        href: campaigns.index(),
        icon: Megaphone,
    },
];

const accountHelpItems: XChangeNavigationItem[] = [
    {
        title: 'Account',
        description: 'Position and connected services',
        href: accounts.index(),
        icon: UsersRound,
    },
    {
        title: 'Guides',
        description: 'Workflows and terminology',
        href: documentation(),
        icon: BookOpen,
    },
];

type XChangeSharedProps = {
    xchange?: {
        navigation?: {
            system_readiness_visible?: boolean;
            commercial_controls_visible?: boolean;
        };
    };
};

const page = usePage<XChangeSharedProps>();
const systemItems = computed<XChangeNavigationItem[]>(() => [
    ...(page.props.xchange?.navigation?.commercial_controls_visible
        ? [
              {
                  title: 'Commercial Controls',
                  description: 'Price List, Waterfall, and earnings',
                  href: commercial.index(),
                  icon: Scale,
              },
          ]
        : []),
    ...(page.props.xchange?.navigation?.system_readiness_visible
        ? [
              {
                  title: 'System Readiness',
                  description: 'Deployment and runtime checks',
                  href: diagnostics.runtimeProfile(),
                  icon: RadioTower,
              },
          ]
        : []),
]);

const navigationGroups = computed(() => [
    { label: 'Workspace', items: workspaceItems },
    { label: 'Account & Help', items: accountHelpItems },
    ...(systemItems.value.length > 0
        ? [{ label: 'System', items: systemItems.value }]
        : []),
]);

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        data-testid="x-change-application-sidebar"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link
                            :href="dashboard()"
                            aria-label="Open Cockpit overview"
                        >
                            <AppLogoIcon class-name="h-8 w-auto shrink-0" />
                            <span
                                class="min-w-0 flex-1 group-data-[collapsible=icon]:hidden"
                            >
                                <span class="block truncate font-semibold">
                                    X-Change
                                </span>
                            </span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup
                v-for="group in navigationGroups"
                :key="group.label"
                class="px-2 py-0"
            >
                <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="item in group.items"
                        :key="item.title"
                    >
                        <SidebarMenuButton
                            as-child
                            size="lg"
                            :is-active="isCurrentUrl(item.href)"
                            :tooltip="`${item.title} — ${item.description}`"
                        >
                            <Link :href="item.href" prefetch>
                                <component :is="item.icon" class="mt-0.5" />
                                <span
                                    class="min-w-0 flex-1 group-data-[collapsible=icon]:hidden"
                                >
                                    <span class="block truncate font-medium">
                                        {{ item.title }}
                                    </span>
                                    <span
                                        class="block truncate text-[0.7rem] leading-4 text-sidebar-foreground/60"
                                    >
                                        {{ item.description }}
                                    </span>
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
