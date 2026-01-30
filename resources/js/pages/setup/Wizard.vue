<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SetupWizardController from '@/actions/App/Http/Controllers/SetupWizardController';
import { dashboard } from '@/routes';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Check,
    Github,
    Bot,
    Database,
    Sparkles,
    ArrowRight,
    ArrowLeft,
    ExternalLink,
    Eye,
    EyeOff,
    Loader2,
    CheckCircle,
    XCircle,
    Lock,
    Globe,
    Search,
    AlertCircle,
} from 'lucide-vue-next';

interface GitHubRepository {
    id: number;
    name: string;
    full_name: string;
    description: string | null;
    private: boolean;
    default_branch: string;
}

const props = defineProps<{
    currentStep: string;
    stepNumber: number;
    totalSteps: number;
    completedSteps: string[];
    // Step-specific data
    connected?: boolean;
    username?: string;
    hasAnthropicKey?: boolean;
    hasOpenaiKey?: boolean;
    defaultProvider?: string;
    repositories?: GitHubRepository[];
    connectedRepositories?: number[];
    hasRepositories?: boolean;
    hasAiProvider?: boolean;
}>();

const steps = [
    { key: 'welcome', label: 'Welcome', icon: Sparkles },
    { key: 'account', label: 'Account', icon: Database },
    { key: 'github', label: 'GitHub', icon: Github },
    { key: 'ai-provider', label: 'AI Provider', icon: Bot },
    { key: 'repository', label: 'Repository', icon: Database },
    { key: 'complete', label: 'Complete', icon: Check },
];

// Account form
const accountForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

// AI Provider form
const aiProviderForm = useForm({
    provider: 'anthropic',
    api_key: '',
    skip: false,
});

const showApiKey = ref(false);
const testingApiKey = ref(false);
const apiKeyTestResult = ref<{ valid: boolean; message: string; models: string[] } | null>(null);

// Repository form
const repositoryForm = useForm({
    github_id: null as number | null,
    skip: false,
});

const repoSearch = ref('');

const filteredRepositories = computed(() => {
    if (!props.repositories) return [];
    if (!repoSearch.value) return props.repositories;
    const search = repoSearch.value.toLowerCase();
    return props.repositories.filter(
        (repo) =>
            repo.name.toLowerCase().includes(search) ||
            repo.full_name.toLowerCase().includes(search) ||
            (repo.description && repo.description.toLowerCase().includes(search))
    );
});

const isStepCompleted = (stepKey: string) => {
    return props.completedSteps.includes(stepKey);
};

const canGoToStep = (stepKey: string) => {
    const stepIndex = steps.findIndex((s) => s.key === stepKey);
    const currentIndex = steps.findIndex((s) => s.key === props.currentStep);

    // Can always go back
    if (stepIndex < currentIndex) return true;

    // Can go to next step only if current is completed
    return isStepCompleted(props.currentStep);
};

const submitAccount = () => {
    accountForm.post(SetupWizardController.createAccount().url);
};

const testApiKey = async () => {
    testingApiKey.value = true;
    apiKeyTestResult.value = null;

    try {
        const response = await fetch(SetupWizardController.testAiProvider().url, {
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
        apiKeyTestResult.value = await response.json();
    } catch (error) {
        apiKeyTestResult.value = {
            valid: false,
            message: 'Failed to test API key. Please check your connection.',
            models: [],
        };
    } finally {
        testingApiKey.value = false;
    }
};

const submitAiProvider = () => {
    aiProviderForm.post(SetupWizardController.saveAiProvider().url);
};

const skipAiProvider = () => {
    aiProviderForm.skip = true;
    aiProviderForm.post(SetupWizardController.saveAiProvider().url);
};

const selectRepository = (repo: GitHubRepository) => {
    repositoryForm.github_id = repo.id;
};

const submitRepository = () => {
    repositoryForm.post(SetupWizardController.saveRepository().url);
};

const skipRepository = () => {
    repositoryForm.skip = true;
    repositoryForm.post(SetupWizardController.saveRepository().url);
};

const goToDashboard = () => {
    router.visit(dashboard().url);
};
</script>

<template>
    <Head title="Setup Wizard" />

    <div class="min-h-screen bg-background flex flex-col">
        <!-- Progress Indicator -->
        <div class="border-b bg-card/50">
            <div class="max-w-3xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between">
                    <div
                        v-for="(step, index) in steps"
                        :key="step.key"
                        class="flex items-center"
                    >
                        <Link
                            v-if="canGoToStep(step.key)"
                            :href="SetupWizardController.show(step.key).url"
                            class="flex items-center gap-2"
                        >
                            <div
                                :class="[
                                    'flex items-center justify-center w-8 h-8 rounded-full border-2 transition-colors',
                                    isStepCompleted(step.key) ? 'bg-primary border-primary text-primary-foreground' :
                                    step.key === currentStep ? 'border-primary text-primary' :
                                    'border-muted text-muted-foreground'
                                ]"
                            >
                                <Check v-if="isStepCompleted(step.key)" class="h-4 w-4" />
                                <span v-else class="text-sm font-medium">{{ index + 1 }}</span>
                            </div>
                            <span
                                :class="[
                                    'text-sm font-medium hidden sm:inline',
                                    step.key === currentStep ? 'text-foreground' : 'text-muted-foreground'
                                ]"
                            >
                                {{ step.label }}
                            </span>
                        </Link>
                        <div v-else class="flex items-center gap-2 opacity-50">
                            <div class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-muted text-muted-foreground">
                                <span class="text-sm font-medium">{{ index + 1 }}</span>
                            </div>
                            <span class="text-sm font-medium hidden sm:inline text-muted-foreground">
                                {{ step.label }}
                            </span>
                        </div>
                        <div
                            v-if="index < steps.length - 1"
                            :class="[
                                'w-8 sm:w-12 h-0.5 mx-2',
                                isStepCompleted(step.key) ? 'bg-primary' : 'bg-muted'
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Step Content -->
        <div class="flex-1 flex items-center justify-center p-4">
            <div class="w-full max-w-xl">
                <!-- Welcome Step -->
                <Card v-if="currentStep === 'welcome'">
                    <CardHeader class="text-center">
                        <div class="flex justify-center mb-4">
                            <div class="p-4 rounded-full bg-primary/10">
                                <Sparkles class="h-12 w-12 text-primary" />
                            </div>
                        </div>
                        <CardTitle class="text-3xl">Welcome to LaraLedger!</CardTitle>
                        <CardDescription class="text-base mt-2">
                            AI-powered release intelligence for Laravel projects
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <p class="text-muted-foreground text-center">
                            LaraLedger helps you manage releases with intelligent version recommendations
                            and automated changelog generation.
                        </p>

                        <div class="space-y-3">
                            <p class="font-medium text-center">We'll help you:</p>
                            <div class="grid gap-3">
                                <div class="flex items-center gap-3 p-3 rounded-lg bg-muted/50">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-primary/20 flex items-center justify-center">
                                        <span class="text-xs font-medium text-primary">1</span>
                                    </div>
                                    <span class="text-sm">Create your account</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-lg bg-muted/50">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-primary/20 flex items-center justify-center">
                                        <span class="text-xs font-medium text-primary">2</span>
                                    </div>
                                    <span class="text-sm">Connect GitHub</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-lg bg-muted/50">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-primary/20 flex items-center justify-center">
                                        <span class="text-xs font-medium text-primary">3</span>
                                    </div>
                                    <span class="text-sm">Configure AI</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-lg bg-muted/50">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-primary/20 flex items-center justify-center">
                                        <span class="text-xs font-medium text-primary">4</span>
                                    </div>
                                    <span class="text-sm">Add your first repository</span>
                                </div>
                            </div>
                        </div>

                        <p class="text-sm text-muted-foreground text-center">
                            This takes about 5 minutes.
                        </p>

                        <Link :href="SetupWizardController.show('account').url" class="block">
                            <Button class="w-full" size="lg">
                                Get Started
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Account Creation Step -->
                <Card v-else-if="currentStep === 'account'">
                    <CardHeader>
                        <CardTitle>Create Your Account</CardTitle>
                        <CardDescription>
                            Set up your administrator account for LaraLedger.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitAccount" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    v-model="accountForm.name"
                                    placeholder="Your name"
                                    required
                                />
                                <p v-if="accountForm.errors.name" class="text-sm text-destructive">
                                    {{ accountForm.errors.name }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    v-model="accountForm.email"
                                    placeholder="you@example.com"
                                    required
                                />
                                <p v-if="accountForm.errors.email" class="text-sm text-destructive">
                                    {{ accountForm.errors.email }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    v-model="accountForm.password"
                                    placeholder="Minimum 8 characters"
                                    required
                                />
                                <p v-if="accountForm.errors.password" class="text-sm text-destructive">
                                    {{ accountForm.errors.password }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="password_confirmation">Confirm Password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    v-model="accountForm.password_confirmation"
                                    placeholder="Confirm your password"
                                    required
                                />
                            </div>

                            <div class="flex justify-between pt-4">
                                <Link :href="SetupWizardController.show('welcome').url">
                                    <Button type="button" variant="outline">
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        Back
                                    </Button>
                                </Link>
                                <Button type="submit" :disabled="accountForm.processing">
                                    {{ accountForm.processing ? 'Creating...' : 'Create Account' }}
                                    <ArrowRight class="ml-2 h-4 w-4" />
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- GitHub Connection Step -->
                <Card v-else-if="currentStep === 'github'">
                    <CardHeader>
                        <CardTitle>Connect GitHub</CardTitle>
                        <CardDescription>
                            LaraLedger needs access to your GitHub repositories to analyze releases.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div v-if="connected" class="p-4 rounded-lg bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800">
                            <div class="flex items-center gap-3">
                                <CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
                                <div>
                                    <p class="font-medium text-green-900 dark:text-green-100">GitHub Connected</p>
                                    <p class="text-sm text-green-700 dark:text-green-300">@{{ username }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="p-4 rounded-lg bg-muted/50">
                                <p class="text-sm font-medium mb-2">Required Permissions:</p>
                                <ul class="text-sm text-muted-foreground space-y-1">
                                    <li class="flex items-center gap-2">
                                        <Check class="h-3 w-3 text-primary" />
                                        Access your repositories (read code, commits, tags)
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <Check class="h-3 w-3 text-primary" />
                                        Read your user profile
                                    </li>
                                </ul>
                            </div>

                            <a :href="SetupWizardController.redirectToGitHub().url" class="block">
                                <Button class="w-full" size="lg">
                                    <Github class="mr-2 h-5 w-5" />
                                    Connect GitHub
                                </Button>
                            </a>
                        </div>

                        <div v-if="connected" class="flex justify-between pt-4">
                            <Link :href="SetupWizardController.show('account').url">
                                <Button type="button" variant="outline">
                                    <ArrowLeft class="mr-2 h-4 w-4" />
                                    Back
                                </Button>
                            </Link>
                            <Link :href="SetupWizardController.show('ai-provider').url">
                                <Button>
                                    Continue
                                    <ArrowRight class="ml-2 h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <!-- AI Provider Step -->
                <Card v-else-if="currentStep === 'ai-provider'">
                    <CardHeader>
                        <CardTitle>Configure AI Provider</CardTitle>
                        <CardDescription>
                            LaraLedger uses AI to classify changes and generate release notes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitAiProvider" class="space-y-6">
                            <!-- Provider Selection -->
                            <div class="space-y-3">
                                <Label>Select Provider</Label>
                                <div class="grid gap-3">
                                    <label
                                        :class="[
                                            'flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors',
                                            aiProviderForm.provider === 'anthropic'
                                                ? 'border-primary bg-primary/5'
                                                : 'border-muted hover:border-muted-foreground/50'
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            v-model="aiProviderForm.provider"
                                            value="anthropic"
                                            class="sr-only"
                                        />
                                        <div class="flex-1">
                                            <p class="font-medium">
                                                Anthropic (Claude)
                                                <Badge variant="secondary" class="ml-2">Recommended</Badge>
                                            </p>
                                            <p class="text-sm text-muted-foreground">Best accuracy for code analysis</p>
                                        </div>
                                        <div v-if="aiProviderForm.provider === 'anthropic'" class="text-primary">
                                            <CheckCircle class="h-5 w-5" />
                                        </div>
                                    </label>

                                    <label
                                        :class="[
                                            'flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors',
                                            aiProviderForm.provider === 'openai'
                                                ? 'border-primary bg-primary/5'
                                                : 'border-muted hover:border-muted-foreground/50'
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            v-model="aiProviderForm.provider"
                                            value="openai"
                                            class="sr-only"
                                        />
                                        <div class="flex-1">
                                            <p class="font-medium">OpenAI (GPT)</p>
                                            <p class="text-sm text-muted-foreground">Wide model selection</p>
                                        </div>
                                        <div v-if="aiProviderForm.provider === 'openai'" class="text-primary">
                                            <CheckCircle class="h-5 w-5" />
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- API Key Input -->
                            <div class="space-y-2">
                                <Label for="api_key">API Key</Label>
                                <div class="relative">
                                    <Input
                                        id="api_key"
                                        :type="showApiKey ? 'text' : 'password'"
                                        v-model="aiProviderForm.api_key"
                                        :placeholder="aiProviderForm.provider === 'anthropic' ? 'sk-ant-...' : 'sk-...'"
                                        class="pr-10"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        @click="showApiKey = !showApiKey"
                                    >
                                        <EyeOff v-if="showApiKey" class="h-4 w-4" />
                                        <Eye v-else class="h-4 w-4" />
                                    </button>
                                </div>
                                <div class="flex items-center gap-4 text-sm">
                                    <a
                                        :href="aiProviderForm.provider === 'anthropic' ? 'https://console.anthropic.com/settings/keys' : 'https://platform.openai.com/api-keys'"
                                        target="_blank"
                                        class="text-primary hover:underline flex items-center gap-1"
                                    >
                                        Get an API Key
                                        <ExternalLink class="h-3 w-3" />
                                    </a>
                                </div>
                            </div>

                            <!-- Test Button -->
                            <div class="space-y-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="!aiProviderForm.api_key || testingApiKey"
                                    @click="testApiKey"
                                >
                                    <Loader2 v-if="testingApiKey" class="mr-2 h-4 w-4 animate-spin" />
                                    Test API Key
                                </Button>

                                <div v-if="apiKeyTestResult" :class="[
                                    'p-3 rounded-lg',
                                    apiKeyTestResult.valid ? 'bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800'
                                ]">
                                    <div class="flex items-center gap-2">
                                        <CheckCircle v-if="apiKeyTestResult.valid" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-4 w-4 text-red-600 dark:text-red-400" />
                                        <span :class="apiKeyTestResult.valid ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100'">
                                            {{ apiKeyTestResult.message }}
                                        </span>
                                    </div>
                                    <p v-if="apiKeyTestResult.valid && apiKeyTestResult.models.length > 0" class="text-sm text-muted-foreground mt-1">
                                        Available models: {{ apiKeyTestResult.models.join(', ') }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex justify-between pt-4">
                                <Link :href="SetupWizardController.show('github').url">
                                    <Button type="button" variant="outline">
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        Back
                                    </Button>
                                </Link>
                                <div class="flex gap-2">
                                    <Button type="button" variant="ghost" @click="skipAiProvider">
                                        Skip for now
                                    </Button>
                                    <Button type="submit" :disabled="aiProviderForm.processing || !aiProviderForm.api_key">
                                        {{ aiProviderForm.processing ? 'Saving...' : 'Continue' }}
                                        <ArrowRight class="ml-2 h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Repository Selection Step -->
                <Card v-else-if="currentStep === 'repository'">
                    <CardHeader>
                        <CardTitle>Add Your First Repository</CardTitle>
                        <CardDescription>
                            Select a repository to start tracking releases.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitRepository" class="space-y-4">
                            <!-- Search -->
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    v-model="repoSearch"
                                    placeholder="Search repositories..."
                                    class="pl-10"
                                />
                            </div>

                            <!-- Repository List -->
                            <div class="max-h-64 overflow-y-auto space-y-2 border rounded-lg p-2">
                                <div v-if="filteredRepositories.length === 0" class="p-4 text-center text-muted-foreground">
                                    <AlertCircle class="h-8 w-8 mx-auto mb-2" />
                                    <p>No repositories found</p>
                                </div>
                                <label
                                    v-for="repo in filteredRepositories"
                                    :key="repo.id"
                                    :class="[
                                        'flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors',
                                        repositoryForm.github_id === repo.id
                                            ? 'bg-primary/10 border-2 border-primary'
                                            : 'hover:bg-muted/50 border-2 border-transparent'
                                    ]"
                                >
                                    <input
                                        type="radio"
                                        :value="repo.id"
                                        v-model="repositoryForm.github_id"
                                        class="sr-only"
                                        @change="selectRepository(repo)"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="font-medium truncate">{{ repo.name }}</p>
                                            <Badge v-if="repo.private" variant="secondary" class="flex-shrink-0">
                                                <Lock class="h-3 w-3 mr-1" />
                                                Private
                                            </Badge>
                                            <Badge v-else variant="outline" class="flex-shrink-0">
                                                <Globe class="h-3 w-3 mr-1" />
                                                Public
                                            </Badge>
                                        </div>
                                        <p v-if="repo.description" class="text-sm text-muted-foreground truncate">
                                            {{ repo.description }}
                                        </p>
                                    </div>
                                    <div v-if="repositoryForm.github_id === repo.id" class="text-primary">
                                        <CheckCircle class="h-5 w-5" />
                                    </div>
                                </label>
                            </div>

                            <div class="flex justify-between pt-4">
                                <Link :href="SetupWizardController.show('ai-provider').url">
                                    <Button type="button" variant="outline">
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        Back
                                    </Button>
                                </Link>
                                <div class="flex gap-2">
                                    <Button type="button" variant="ghost" @click="skipRepository">
                                        Skip for now
                                    </Button>
                                    <Button type="submit" :disabled="repositoryForm.processing || !repositoryForm.github_id">
                                        {{ repositoryForm.processing ? 'Connecting...' : 'Connect Repository' }}
                                        <ArrowRight class="ml-2 h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Completion Step -->
                <Card v-else-if="currentStep === 'complete'">
                    <CardHeader class="text-center">
                        <div class="flex justify-center mb-4">
                            <div class="p-4 rounded-full bg-green-100 dark:bg-green-900">
                                <CheckCircle class="h-12 w-12 text-green-600 dark:text-green-400" />
                            </div>
                        </div>
                        <CardTitle class="text-3xl">You're All Set!</CardTitle>
                        <CardDescription class="text-base mt-2">
                            LaraLedger is ready to help you manage your releases.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Summary -->
                        <div class="space-y-2">
                            <p class="font-medium">Setup Summary:</p>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <CheckCircle class="h-4 w-4 text-green-600" />
                                    <span>GitHub connected</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <CheckCircle v-if="hasAiProvider" class="h-4 w-4 text-green-600" />
                                    <AlertCircle v-else class="h-4 w-4 text-yellow-600" />
                                    <span>{{ hasAiProvider ? 'AI provider configured' : 'AI provider skipped (heuristics only)' }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <CheckCircle v-if="hasRepositories" class="h-4 w-4 text-green-600" />
                                    <AlertCircle v-else class="h-4 w-4 text-yellow-600" />
                                    <span>{{ hasRepositories ? 'Repository connected' : 'No repository added yet' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Next Steps -->
                        <div class="space-y-3">
                            <p class="font-medium">Next Steps:</p>
                            <div class="grid gap-2">
                                <Button class="w-full justify-start" variant="outline" @click="goToDashboard">
                                    <Sparkles class="mr-2 h-4 w-4" />
                                    Explore the Dashboard
                                </Button>
                                <Link v-if="hasRepositories" :href="dashboard().url" class="block">
                                    <Button class="w-full justify-start" variant="outline">
                                        <ArrowRight class="mr-2 h-4 w-4" />
                                        Analyze Your First Release
                                    </Button>
                                </Link>
                            </div>
                        </div>

                        <!-- Tips -->
                        <div class="p-4 rounded-lg bg-muted/50">
                            <p class="text-sm font-medium mb-2">Quick Tips:</p>
                            <ul class="text-sm text-muted-foreground space-y-1">
                                <li>• Start by selecting a tag range to analyze</li>
                                <li>• Review the recommended version bump</li>
                                <li>• Accept, adjust, or reject to improve accuracy over time</li>
                            </ul>
                        </div>

                        <Button class="w-full" size="lg" @click="goToDashboard">
                            Go to Dashboard
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
