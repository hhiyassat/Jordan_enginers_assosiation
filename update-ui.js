const fs = require('fs');
const path = require('path');

function walk(dir) {
    let results = [];
    const list = fs.readdirSync(dir);
    list.forEach(file => {
        file = path.join(dir, file);
        const stat = fs.statSync(file);
        if (stat && stat.isDirectory()) {
            results = results.concat(walk(file));
        } else if (file.endsWith('.ts') || file.endsWith('.tsx')) {
            results.push(file);
        }
    });
    return results;
}

const files = walk('frontend/src');

const mapping = [
    { from: 'platform/ui/Button', to: 'shared/ui' },
    { from: 'platform/ui/Modal', to: 'shared/ui' },
    { from: 'platform/ui/FormField', to: 'shared/ui' },
    { from: 'platform/ui/Bilingual', to: 'shared/ui' },
    { from: 'platform/ui/PageHero', to: 'shared/ui' },
    { from: 'platform/ui/SkipToContent', to: 'shared/ui' },
    { from: 'platform/ui/ConfirmDialog', to: 'shared/ui' },
    { from: 'components/JEALogo', to: 'shared/ui' },
    { from: 'platform/components/ErrorBoundary', to: 'shared/ui' }
];

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    let newContent = content;
    
    mapping.forEach(m => {
        // e.g. from '../../platform/ui/Button' to '../../shared/ui'
        const regex = new RegExp(`(['"\`])((?:\\.\\.\\/)+)${m.from}(['"\`])`, 'g');
        newContent = newContent.replace(regex, `$1$2${m.to}$3`);
        
        // e.g. from '../platform/ui/Button' (if it was somehow one level up)
        const regex2 = new RegExp(`(['"\`])\\.\\/${m.from}(['"\`])`, 'g');
        newContent = newContent.replace(regex2, `$1./${m.to}$2`);
    });
    
    if (content !== newContent) {
        fs.writeFileSync(file, newContent, 'utf8');
        console.log(`Updated ${file}`);
    }
});
