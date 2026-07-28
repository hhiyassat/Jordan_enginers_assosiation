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

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    let newContent = content.replace(/(['"\`])((?:\.\.\/)+)platform\/utils\/csv(['"\`])/g, '$1$2shared/utils$3');
    newContent = newContent.replace(/(['"\`])((?:\.\.\/)+)platform\/utils\/errorMessage(['"\`])/g, '$1$2shared/utils$3');
    newContent = newContent.replace(/(['"\`])((?:\.\.\/)+)platform\/utils\/useSortableRows(['"\`])/g, '$1$2shared/utils$3');
    newContent = newContent.replace(/(['"\`])((?:\.\.\/)+)platform\/utils\/SortHeader(['"\`])/g, '$1$2shared/utils$3');

    if (content !== newContent) {
        fs.writeFileSync(file, newContent, 'utf8');
        console.log(`Updated ${file}`);
    }
});
