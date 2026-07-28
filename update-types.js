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
    let newContent = content.replace(/(['"\`])((?:\.\.\/)+)types(['"\`])/g, '$1$2shared/types$3');
    newContent = newContent.replace(/(['"\`])((?:\.\.\/)+)types\/jea(['"\`])/g, '$1$2shared/types$3');
    newContent = newContent.replace(/(['"\`])((?:\.\.\/)+)types\/platform(['"\`])/g, '$1$2shared/types$3');
    
    if (content !== newContent) {
        fs.writeFileSync(file, newContent, 'utf8');
        console.log(`Updated ${file}`);
    }
});
