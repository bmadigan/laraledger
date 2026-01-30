<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import RepositoryController from '@/actions/App/Http/Controllers/RepositoryController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { X, Plus, AlertTriangle } from 'lucide-vue-next';

interface Branch {
    name: string;
    protected: boolean;
}

interface Repository {
    id: number;
    name: string;
    full_name: string;
    default_branch: string;
    tag_pattern: string;
    ignored_paths: string[] | null;
}

const props = defineProps<{
    repository: Repository;
    branches: Branch[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Repositories',
        href: RepositoryController.index().url,
    },
    {
        title: props.repository.name,
        href: RepositoryController.show(props.repository.id).url,
    },
    {
        title: 'Settings',
        href: RepositoryController.edit(props.repository.id).url,
    },
];

const form = useForm({
    default_branch: props.repository.default_branch,
    tag_pattern: props.repository.tag_pattern,
    ignored_paths: props.repository.ignored_paths || [],
});

const newIgnoredPath = ref('');

const addIgnoredPath = () => {
    if (newIgnoredPath.value && !form.ignored_paths.includes(newIgnoredPath.value)) {
        form.ignored_paths.push(newIgnoredPath.value);
        newIgnoredPath.value = '';
    }
};

const removeIgnoredPath = (path: string) => {
    form.ignored_paths = form.ignored_paths.filter((p) => p !== path);
};

const submit = () => {
    form.put(RepositoryController.update(props.repository.id).url);
};

const deleteRepository = () => {
    if (confirm(`Are you sure you want to disconnect "${props.repository.full_name}"? This will delete all release history for this repository. This action cannot be undone.`)) {
        router.delete(RepositoryController.destroy(props.repository.id).url);
    }
};
</script>

<template>
    <Head :title="`${repository.name} Settings`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-2xl">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Repository Settings</h1>
                <p class="text-muted-foreground">
                    Configure settings for {{ repository.full_name }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- General Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle>General Settings</CardTitle>
                        <CardDescription>
                            Configure how LaraLedger analyzes this repository
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Default Branch -->
                        <div class="space-y-2">
                            <Label for="default_branch">Default Branch</Label>
                            <select
                                id="default_branch"
                                v-model="form.default_branch"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option
                                    v-for="branch in branches"
                                    :key="branch.name"
                                    :value="branch.name"
                                >
                                    {{ branch.name }}{{ branch.protected ? ' (Protected)' : '' }}
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                The branch to use as the default "to" reference when analyzing releases
                            </p>
                        </div>

                        <!-- Tag Pattern -->
                        <div class="space-y-2">
                            <Label for="tag_pattern">Tag Pattern</Label>
                            <Input
                                id="tag_pattern"
                                v-model="form.tag_pattern"
                                placeholder="v*.*.*"
                            />
                            <p class="text-xs text-muted-foreground">
                                Glob pattern for matching version tags (e.g., v*.*.*, release-*)
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Ignored Paths -->
                <Card>
                    <CardHeader>
                        <CardTitle>Ignored Paths</CardTitle>
                        <CardDescription>
                            Exclude these paths from analysis (changes won't affect version recommendations)
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex gap-2">
                            <Input
                                v-model="newIgnoredPath"
                                placeholder="e.g., tests/, docs/, *.md"
                                @keyup.enter="addIgnoredPath"
                            />
                            <Button type="button" variant="outline" @click="addIgnoredPath">
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                        <div v-if="form.ignored_paths.length > 0" class="flex flex-wrap gap-2">
                            <Badge
                                v-for="path in form.ignored_paths"
                                :key="path"
                                variant="secondary"
                                class="flex items-center gap-1"
                            >
                                <span class="font-mono">{{ path }}</span>
                                <button
                                    type="button"
                                    class="hover:text-destructive"
                                    @click="removeIgnoredPath(path)"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </Badge>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No paths are being ignored. All file changes will be considered during analysis.
                        </p>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex justify-between">
                    <Link :href="RepositoryController.show(repository.id).url">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Settings' }}
                    </Button>
                </div>
            </form>

            <!-- Danger Zone -->
            <Card class="border-destructive/50">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="h-5 w-5" />
                        Danger Zone
                    </CardTitle>
                    <CardDescription>
                        Irreversible actions that affect this repository
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium">Disconnect Repository</h4>
                            <p class="text-sm text-muted-foreground">
                                Remove this repository and delete all its release history
                            </p>
                        </div>
                        <Button variant="destructive" @click="deleteRepository">
                            Disconnect
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
