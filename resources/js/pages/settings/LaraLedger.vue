<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import LaraLedgerSettingsController from '@/actions/App/Http/Controllers/Settings/LaraLedgerSettingsController';
import GitHubController from '@/actions/App/Http/Controllers/Auth/GitHubController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Github,
    Bot,
    Settings,
    Download,
    Trash2,
    CheckCircle,
    XCircle,
    Loader2,
    AlertTriangle,
    Eye,
    EyeOff,
    Database,
    FlaskConical,
} from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

interface Model {
    id: string;
    name: string;
}

interface AvailableModels {
    anthropic: Model[];
    openai: Model[];
}

interface GitHubInfo {
    connected: boolean;
    username: string | null;
    scopes: string[];
}

interface ProviderInfo {
    provider: string;
    hasKey: boolean;
    model: string;
}

interface AnalysisDefaults {
    depth: string;
    noteStyle: string;
    heuristicThreshold: number;
}

const props = defineProps<{
    github: GitHubInfo;
    aiProvider: ProviderInfo;
    generationProvider: ProviderInfo;
    analysisDefaults: AnalysisDefaults;
    availableModels: AvailableModels;
    isDebugMode: boolean;
}>();

// AI Provider Form
const aiProviderForm = useForm({
    provider: props.aiProvider.provider,
    api_key: '',
    model: props.aiProvider.model,
});

const generationProviderForm = useForm({
    provider: props.generationProvider.provider,
    api_key: '',
    model: props.generationProvider.model,
});

const analysisDefaultsForm = useForm({
    depth: props.analysisDefaults.depth,
    note_style: props.analysisDefaults.noteStyle,
    heuristic_threshold: props.analysisDefaults.heuristicThreshold,
});

const clearHistoryForm = useForm({
    confirm: '',
});

const loadingDemoData = ref(false);

const showApiKey = ref(false);
const showGenApiKey = ref(false);
const testingAi = ref(false);
const aiTestResult = ref<{ success: boolean; message: string } | null>(null);

const testAiConnection = async () => {
    if (!aiProviderForm.api_key) {
        aiTestResult.value = { success: false, message: 'Please enter an API key' };
        return;
    }

    testingAi.value = true;
    aiTestResult.value = null;

    try {
        const response = await fetch(LaraLedgerSettingsController.testAiProvider().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                provider: aiProviderForm.provider,
                api_key: aiProviderForm.api_key,
            }),
        });

        const data = await response.json();
        aiTestResult.value = data;
    } catch {
        aiTestResult.value = { success: false, message: 'Connection test failed' };
    } finally {
        testingAi.value = false;
    }
};

const submitAiProvider = () => {
    aiProviderForm.put(LaraLedgerSettingsController.updateAiProvider().url, {
        preserveScroll: true,
    });
};

const submitGenerationProvider = () => {
    generationProviderForm.put(LaraLedgerSettingsController.updateGenerationProvider().url, {
        preserveScroll: true,
    });
};

const submitAnalysisDefaults = () => {
    analysisDefaultsForm.put(LaraLedgerSettingsController.updateAnalysisDefaults().url, {
        preserveScroll: true,
    });
};

const submitClearHistory = () => {
    if (clearHistoryForm.confirm !== 'DELETE ALL') return;

    clearHistoryForm.delete(LaraLedgerSettingsController.clearHistory().url, {
        preserveScroll: true,
        onSuccess: () => {
            clearHistoryForm.confirm = '';
        },
    });
};

const exportData = () => {
    window.location.href = LaraLedgerSettingsController.exportData().url;
};

const disconnectGithub = () => {
    if (confirm('Are you sure you want to disconnect GitHub? This will remove access to all repositories.')) {
        router.delete(GitHubController.disconnect().url);
    }
};

const loadDemoData = () => {
    if (!confirm('This will create demo repositories and releases. Continue?')) {
        return;
    }

    loadingDemoData.value = true;
    router.post(LaraLedgerSettingsController.loadDemoData().url, {}, {
        onFinish: () => {
            loadingDemoData.value = false;
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'LaraLedger Settings', href: LaraLedgerSettingsController.index().url }]">
        <Head title="LaraLedger Settings" />

        <SettingsLayout>
        <div class="space-y-6">
            <!-- GitHub Connection -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Github class="h-5 w-5" />
                        GitHub Connection
                    </CardTitle>
                    <CardDescription>Manage your GitHub OAuth connection</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="github.connected" class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <CheckCircle class="h-5 w-5 text-green-500" />
                            <div>
                                <p class="font-medium">Connected as {{ github.username }}</p>
                                <p class="text-sm text-muted-foreground">
                                    Scopes: {{ github.scopes.join(', ') || 'none' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" as="a" :href="GitHubController.redirect().url">
                                Reconnect
                            </Button>
                            <Button variant="destructive" @click="disconnectGithub">
                                Disconnect
                            </Button>
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <XCircle class="h-5 w-5 text-red-500" />
                            <p>Not connected</p>
                        </div>
                        <Button as="a" :href="GitHubController.redirect().url">
                            <Github class="mr-2 h-4 w-4" />
                            Connect GitHub
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- AI Classification Provider -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Bot class="h-5 w-5" />
                        AI Classification Provider
                    </CardTitle>
                    <CardDescription>
                        Configure the AI provider for version classification. This model is used when heuristic confidence is below threshold.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitAiProvider" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Provider</Label>
                                <select
                                    v-model="aiProviderForm.provider"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                >
                                    <option value="anthropic">Anthropic (Claude)</option>
                                    <option value="openai">OpenAI (GPT)</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Model</Label>
                                <select
                                    v-model="aiProviderForm.model"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                >
                                    <option
                                        v-for="model in availableModels[aiProviderForm.provider as keyof AvailableModels]"
                                        :key="model.id"
                                        :value="model.id"
                                    >
                                        {{ model.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>API Key</Label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Input
                                        v-model="aiProviderForm.api_key"
                                        :type="showApiKey ? 'text' : 'password'"
                                        :placeholder="aiProvider.hasKey ? '••••••••••••••••' : 'Enter API key'"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-3 top-1/2 -translate-y-1/2"
                                        @click="showApiKey = !showApiKey"
                                    >
                                        <Eye v-if="!showApiKey" class="h-4 w-4 text-muted-foreground" />
                                        <EyeOff v-else class="h-4 w-4 text-muted-foreground" />
                                    </button>
                                </div>
                                <Button type="button" variant="outline" :disabled="testingAi" @click="testAiConnection">
                                    <Loader2 v-if="testingAi" class="mr-2 h-4 w-4 animate-spin" />
                                    Test
                                </Button>
                            </div>
                            <p v-if="aiProvider.hasKey" class="text-xs text-muted-foreground">
                                Leave blank to keep existing key
                            </p>
                        </div>

                        <div v-if="aiTestResult" class="p-3 rounded-lg" :class="aiTestResult.success ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                            <p class="text-sm" :class="aiTestResult.success ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                                {{ aiTestResult.message }}
                            </p>
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="aiProviderForm.processing">
                                Save Classification Settings
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- AI Generation Provider -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Bot class="h-5 w-5" />
                        AI Generation Provider
                    </CardTitle>
                    <CardDescription>
                        Configure the AI provider for release note generation. A more capable model produces better prose.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitGenerationProvider" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Provider</Label>
                                <select
                                    v-model="generationProviderForm.provider"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                >
                                    <option value="anthropic">Anthropic (Claude)</option>
                                    <option value="openai">OpenAI (GPT)</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Model</Label>
                                <select
                                    v-model="generationProviderForm.model"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                >
                                    <option
                                        v-for="model in availableModels[generationProviderForm.provider as keyof AvailableModels]"
                                        :key="model.id"
                                        :value="model.id"
                                    >
                                        {{ model.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>API Key (optional)</Label>
                            <div class="relative">
                                <Input
                                    v-model="generationProviderForm.api_key"
                                    :type="showGenApiKey ? 'text' : 'password'"
                                    placeholder="Leave blank to use classification provider key"
                                />
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2"
                                    @click="showGenApiKey = !showGenApiKey"
                                >
                                    <Eye v-if="!showGenApiKey" class="h-4 w-4 text-muted-foreground" />
                                    <EyeOff v-else class="h-4 w-4 text-muted-foreground" />
                                </button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Leave blank to use the same key as the classification provider
                            </p>
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="generationProviderForm.processing">
                                Save Generation Settings
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Analysis Defaults -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Analysis Defaults
                    </CardTitle>
                    <CardDescription>Configure default analysis behavior (can be overridden per-analysis)</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitAnalysisDefaults" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <Label>Default Depth</Label>
                                <select
                                    v-model="analysisDefaultsForm.depth"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                >
                                    <option value="standard">Standard</option>
                                    <option value="deep">Deep (include diff analysis)</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Default Note Style</Label>
                                <select
                                    v-model="analysisDefaultsForm.note_style"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                >
                                    <option value="technical">Technical</option>
                                    <option value="user_friendly">User-Friendly</option>
                                    <option value="marketing">Marketing</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Heuristic Threshold (%)</Label>
                                <Input
                                    v-model.number="analysisDefaultsForm.heuristic_threshold"
                                    type="number"
                                    min="50"
                                    max="100"
                                />
                                <p class="text-xs text-muted-foreground">
                                    AI used when confidence below this threshold
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="analysisDefaultsForm.processing">
                                Save Defaults
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Data Management -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Database class="h-5 w-5" />
                        Data Management
                    </CardTitle>
                    <CardDescription>Export your data or clear analysis history</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Demo Data (Development Only) -->
                    <div v-if="isDebugMode" class="flex items-center justify-between p-4 rounded-lg border border-dashed border-yellow-500/50 bg-yellow-50/50 dark:bg-yellow-900/10">
                        <div class="flex items-center gap-3">
                            <FlaskConical class="h-5 w-5 text-yellow-600" />
                            <div>
                                <p class="font-medium">Load Demo Data</p>
                                <p class="text-sm text-muted-foreground">
                                    Create sample repositories and releases for testing
                                </p>
                            </div>
                        </div>
                        <Button variant="outline" :disabled="loadingDemoData" @click="loadDemoData">
                            <Loader2 v-if="loadingDemoData" class="mr-2 h-4 w-4 animate-spin" />
                            <FlaskConical v-else class="mr-2 h-4 w-4" />
                            {{ loadingDemoData ? 'Loading...' : 'Load Demo Data' }}
                        </Button>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Export All Data</p>
                            <p class="text-sm text-muted-foreground">Download all releases, settings, and history as JSON</p>
                        </div>
                        <Button variant="outline" @click="exportData">
                            <Download class="mr-2 h-4 w-4" />
                            Export JSON
                        </Button>
                    </div>

                    <div class="border-t pt-6">
                        <div class="flex items-start gap-4">
                            <AlertTriangle class="h-5 w-5 text-destructive mt-0.5" />
                            <div class="flex-1">
                                <p class="font-medium text-destructive">Clear Analysis History</p>
                                <p class="text-sm text-muted-foreground mb-4">
                                    This will permanently delete all release analyses. Repositories will remain connected.
                                </p>
                                <div class="flex items-end gap-2">
                                    <div class="space-y-1">
                                        <Label class="text-xs">Type "DELETE ALL" to confirm</Label>
                                        <Input
                                            v-model="clearHistoryForm.confirm"
                                            placeholder="DELETE ALL"
                                            class="w-48"
                                        />
                                    </div>
                                    <Button
                                        variant="destructive"
                                        :disabled="clearHistoryForm.confirm !== 'DELETE ALL' || clearHistoryForm.processing"
                                        @click="submitClearHistory"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Clear History
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
        </SettingsLayout>
    </AppLayout>
</template>
